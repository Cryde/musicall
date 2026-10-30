<?php

declare(strict_types=1);

namespace App\Tests\Api\OAuth;

use App\Controller\OAuth\AppleController;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\OAuth\Apple\AppleSignInAttempt;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeAppleIdentityTokenVerifier;
use App\Tests\Double\FakeAppleTokenClient;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Sign in with Apple on the site (#1271). Apple itself is a double: the identity token it posts is
 * genuine only if a test says so, for the website's Services ID (`com.musicall.web` in .env.test).
 */
#[ResetDatabase]
class AppleOAuthTest extends ApiTestCase
{
    private const string APPLE_ID = '001234.0123456789abcdef.1234';
    private const string WEB_CLIENT_ID = 'com.musicall.web';
    private const string FRONTEND_URL = 'http://localhost';
    private const string CALLBACK_URL = 'https://musicall.test/oauth/apple/callback';

    public function test_start_sends_the_visitor_to_apple_with_a_fresh_attempt(): void
    {
        $this->client->request('GET', '/oauth/apple?return_url=' . urlencode('/band-space/abc'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://appleid.apple.com/auth/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $attempt = AppleSignInAttempt::fromCookie($this->attemptCookie()->getValue() ?? '');
        $this->assertInstanceOf(AppleSignInAttempt::class, $attempt);
        $this->assertSame([
            'client_id' => self::WEB_CLIENT_ID,
            'redirect_uri' => 'http://musicall.test/oauth/apple/callback',
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $attempt->state,
            'nonce' => $attempt->nonce,
        ], $query);
        $this->assertSame('/band-space/abc', $attempt->returnUrl);
    }

    /** Sent with Apple's cross-site POST, and a `__Host-` cookie, which no sibling subdomain can plant. */
    public function test_the_attempt_cookie_survives_apples_cross_site_post(): void
    {
        $this->client->request('GET', '/oauth/apple');

        $cookie = $this->attemptCookie();
        $this->assertSame('__Host-apple_sign_in', $cookie->getName());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_NONE, $cookie->getSameSite());
        $this->assertEqualsWithDelta(time() + 600, $cookie->getExpiresTime(), 5);
    }

    public function test_start_keeps_no_return_url_that_leaves_the_origin_or_outgrows_a_cookie(): void
    {
        foreach (['//evil.example', '/\\evil.example', 'https://evil.example', '/' . str_repeat('a', 2048)] as $returnUrl) {
            $this->client->request('GET', '/oauth/apple?return_url=' . urlencode($returnUrl));

            $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
            $this->assertNull(AppleSignInAttempt::fromCookie($this->attemptCookie()->getValue() ?? '')?->returnUrl, sprintf('%s must not be kept', json_encode($returnUrl)));
        }
    }

    public function test_a_first_sign_in_creates_an_account_named_after_the_person(): void
    {
        $attempt = AppleSignInAttempt::start('/band-space/abc');
        $this->apple()->accept('an-id-token', $this->claims('x7k2@privaterelay.appleid.com', $attempt->nonce), self::WEB_CLIENT_ID);
        $this->appleTokens()->willExchange('a-code', self::APPLE_ID, 'r.apple-refresh-token');

        $this->postToCallback($attempt, [
            'state' => $attempt->state,
            'code' => 'a-code',
            'id_token' => 'an-id-token',
            'user' => '{"name":{"firstName":"Élodie","lastName":"Dupré"},"email":"x7k2@privaterelay.appleid.com"}',
        ]);

        $this->assertResponseRedirects(self::FRONTEND_URL . '/band-space/abc', Response::HTTP_FOUND);
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'x7k2@privaterelay.appleid.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('elodiedupre', $user->username);
        $this->assertSame('Élodie Dupré', $user->profile->displayName);
        $this->assertNotNull($user->confirmationDatetime);
        $this->assertSignedInAs('elodiedupre');
        $this->assertSame(['refresh_token' => 'r.apple-refresh-token', 'refresh_token_client_id' => self::WEB_CLIENT_ID], $this->storedRefreshToken());
        $this->assertSame([['code' => 'a-code', 'client_id' => self::WEB_CLIENT_ID]], $this->appleTokens()->exchanges);
    }

    /** The same Apple ID as the app's, since the Services ID is grouped under its App ID. */
    public function test_an_account_created_in_the_app_signs_in_on_the_site(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com', 'password' => null]);
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_APPLE, 'providerId' => self::APPLE_ID]);
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('alice@example.com', $attempt->nonce), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertResponseRedirects(self::FRONTEND_URL, Response::HTTP_FOUND);
        $this->assertSignedInAs('alice_drums');
    }

    /** Apple failing the exchange costs the refresh token, not the sign-in. */
    public function test_a_code_apple_refuses_still_signs_in(): void
    {
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('nouveau@example.com', $attempt->nonce), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'code' => 'a-used-code', 'id_token' => 'an-id-token']);

        $this->assertResponseRedirects(self::FRONTEND_URL, Response::HTTP_FOUND);
        $this->assertSignedInAs('nouveau');
        $this->assertSame(['refresh_token' => null, 'refresh_token_client_id' => null], $this->storedRefreshToken());
    }

    public function test_an_email_owned_by_an_existing_account_is_refused(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('alice@example.com', $attempt->nonce), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'code' => 'a-code', 'id_token' => 'an-id-token']);

        $this->assertRefused('email_exists');
        $this->assertSame([], $this->appleTokens()->exchanges);
    }

    public function test_an_unverified_email_is_refused(): void
    {
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('unverified@example.com', $attempt->nonce, verified: false), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertRefused('email_not_verified');
    }

    public function test_a_suspended_account_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'alice_drums',
            'email' => 'alice@example.com',
            'suspensionDatetime' => new \DateTimeImmutable('2026-10-01'),
        ]);
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_APPLE, 'providerId' => self::APPLE_ID]);
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('alice@example.com', $attempt->nonce), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertRefused('account_suspended');
    }

    /** A callback this browser did not start: login CSRF, or an attempt that expired. */
    public function test_a_callback_without_its_attempt_cookie_is_refused(): void
    {
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->accept('an-id-token', $this->claims('nouveau@example.com', $attempt->nonce), self::WEB_CLIENT_ID);

        $this->postToCallback(null, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertRefused('oauth_failed');
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nouveau@example.com']));
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function mismatchedCallbacks(): iterable
    {
        yield 'another state' => [['state' => 'another-state', 'nonce' => 'the-nonce', 'audience' => self::WEB_CLIENT_ID]];
        yield 'no state' => [['state' => '', 'nonce' => 'the-nonce', 'audience' => self::WEB_CLIENT_ID]];
        yield 'a token for another attempt' => [['state' => 'the-state', 'nonce' => 'another-nonce', 'audience' => self::WEB_CLIENT_ID]];
        yield 'a token without a nonce' => [['state' => 'the-state', 'nonce' => '', 'audience' => self::WEB_CLIENT_ID]];
        yield 'a token issued to the app' => [['state' => 'the-state', 'nonce' => 'the-nonce', 'audience' => 'com.musicall.app']];
    }

    /** @param array{state: string, nonce: string, audience: string} $callback */
    #[DataProvider('mismatchedCallbacks')]
    public function test_a_callback_that_does_not_match_its_attempt_is_refused(array $callback): void
    {
        $attempt = new AppleSignInAttempt('the-state', 'the-nonce', null);
        $claims = $this->claims('nouveau@example.com', $callback['nonce']);
        if ($callback['nonce'] === '') {
            unset($claims['nonce']);
        }
        $this->apple()->accept('an-id-token', $claims, $callback['audience']);

        $this->postToCallback($attempt, ['state' => $callback['state'], 'id_token' => 'an-id-token']);

        $this->assertRefused('oauth_failed');
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nouveau@example.com']));
    }

    /** The return URL is checked where it is used too, not trusted because it sat in our cookie. */
    public function test_a_return_url_that_leaves_the_origin_is_not_followed(): void
    {
        $attempt = new AppleSignInAttempt('the-state', 'the-nonce', 'https://evil.example/phish');
        $this->apple()->accept('an-id-token', $this->claims('nouveau@example.com', 'the-nonce'), self::WEB_CLIENT_ID);

        $this->postToCallback($attempt, ['state' => 'the-state', 'id_token' => 'an-id-token']);

        $this->assertResponseRedirects(self::FRONTEND_URL, Response::HTTP_FOUND);
        $this->assertSignedInAs('nouveau');
    }

    public function test_an_unreadable_attempt_cookie_is_refused(): void
    {
        $this->client->getCookieJar()->set(new BrowserCookie(AppleController::ATTEMPT_COOKIE, 'not-an-attempt', null, '/', 'musicall.test', true, true, false, Cookie::SAMESITE_NONE));

        $this->client->request('POST', self::CALLBACK_URL, ['state' => 'not-an-attempt', 'id_token' => 'an-id-token']);

        $this->assertRefused('oauth_failed');
    }

    /** A field sent as a list is a missing one: the login page, not a 400. */
    public function test_a_field_sent_as_a_list_is_refused_like_a_missing_one(): void
    {
        $attempt = AppleSignInAttempt::start(null);

        $this->postToCallback($attempt, ['state' => [$attempt->state], 'id_token' => ['an-id-token']]);

        $this->assertRefused('oauth_failed');
    }

    /** Whatever goes wrong, the visitor lands on the login page with a message, never on an error page. */
    public function test_an_unexpected_failure_is_refused(): void
    {
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->failWith(new \RuntimeException('Something nobody planned for'));

        $this->postToCallback($attempt, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertRefused('oauth_failed');
    }

    /** The person closed Apple's page: Apple posts `error=user_cancelled_authorize` and no token. */
    public function test_a_sign_in_cancelled_on_apple_is_refused(): void
    {
        $attempt = AppleSignInAttempt::start(null);

        $this->postToCallback($attempt, ['state' => $attempt->state, 'error' => 'user_cancelled_authorize']);

        $this->assertRefused('oauth_failed');
    }

    public function test_apple_unreachable_is_refused(): void
    {
        $attempt = AppleSignInAttempt::start(null);
        $this->apple()->becomeUnreachable();

        $this->postToCallback($attempt, ['state' => $attempt->state, 'id_token' => 'an-id-token']);

        $this->assertRefused('oauth_failed');
    }

    /** @param array<string, string|list<string>> $form */
    private function postToCallback(?AppleSignInAttempt $attempt, array $form): void
    {
        if ($attempt instanceof AppleSignInAttempt) {
            $this->client->getCookieJar()->set(new BrowserCookie(AppleController::ATTEMPT_COOKIE, $attempt->toCookie(), null, '/', 'musicall.test', true, true, false, Cookie::SAMESITE_NONE));
        }
        $this->client->request('POST', self::CALLBACK_URL, $form);
    }

    /** @return array<string, mixed> */
    private function claims(string $email, string $nonce, bool $verified = true): array
    {
        return [
            'iss' => 'https://appleid.apple.com',
            'aud' => self::WEB_CLIENT_ID,
            'sub' => self::APPLE_ID,
            'email' => $email,
            'email_verified' => $verified ? 'true' : 'false',
            'nonce' => $nonce,
        ];
    }

    private function attemptCookie(): Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === AppleController::ATTEMPT_COOKIE) {
                return $cookie;
            }
        }
        $this->fail('No attempt cookie was set');
    }

    /** The session cookies, and the attempt cookie cleared: it is single use. */
    private function assertSignedInAs(string $username): void
    {
        $cookies = [];
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }
        $names = array_keys($cookies);
        sort($names);
        $this->assertSame(['__Host-apple_sign_in', '__Secure-mercure_access_token', 'jwt_hp', 'jwt_s', 'refresh_token'], $names);
        $this->assertTrue($cookies[AppleController::ATTEMPT_COOKIE]->isCleared());
        $this->assertSame($username, JwtPayload::of($cookies['jwt_hp']->getValue() . '.signature')['username']);
    }

    private function assertRefused(string $error): void
    {
        $this->assertResponseRedirects(self::FRONTEND_URL . '/login?oauth_error=' . $error, Response::HTTP_FOUND);
        $cookies = $this->client->getResponse()->headers->getCookies();
        $this->assertCount(1, $cookies, 'Only the attempt cookie, cleared: no session is issued');
        $this->assertSame(AppleController::ATTEMPT_COOKIE, $cookies[0]->getName());
        $this->assertTrue($cookies[0]->isCleared());
    }

    /** @return array{refresh_token: ?string, refresh_token_client_id: ?string} */
    private function storedRefreshToken(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $account = $entityManager->getRepository(SocialAccount::class)->findOneBy(['providerId' => self::APPLE_ID]);
        $this->assertInstanceOf(SocialAccount::class, $account);

        return ['refresh_token' => $account->refreshToken, 'refresh_token_client_id' => $account->refreshTokenClientId];
    }

    private function apple(): FakeAppleIdentityTokenVerifier
    {
        return self::getContainer()->get(FakeAppleIdentityTokenVerifier::class);
    }

    private function appleTokens(): FakeAppleTokenClient
    {
        return self::getContainer()->get(FakeAppleTokenClient::class);
    }
}
