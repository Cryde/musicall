<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\SocialAccountRepository;
use App\Repository\UserRepository;
use App\Security\Native\NativeOAuthAttemptLimiter;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeAppleIdentityTokenVerifier;
use App\Tests\Double\FakeAppleTokenClient;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Sign in with Apple for the iOS app (#1269): an identity token in, the native session body out.
 * Apple itself is a double here; AppleIdentityTokenVerifierTest and AppleTokenClientTest cover what
 * the real calls refuse.
 */
#[ResetDatabase]
class NativeAppleSignInTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string APPLE_ID = '001234.0123456789abcdef0123456789abcdef.1234';

    public function test_a_first_sign_in_creates_a_confirmed_account_named_after_the_person(): void
    {
        $this->apple()->accept('a-token', $this->claims('x7k2@privaterelay.appleid.com'));
        $this->appleTokens()->willExchange('a-code', self::APPLE_ID, 'r.apple-refresh-token');

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-code', 'given_name' => 'Élodie', 'family_name' => 'Dupré']);

        $this->assertResponseIsSuccessful();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'x7k2@privaterelay.appleid.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('elodiedupre', $user->username);
        $this->assertSame('Élodie Dupré', $user->profile->displayName);
        $this->assertNotNull($user->confirmationDatetime);
        $this->assertNull($user->password);
        $this->assertSame([
            'provider' => 'apple',
            'user' => $user->id,
            'refresh_token' => 'r.apple-refresh-token',
            'refresh_token_client_id' => 'com.musicall.app',
        ], $this->appleAccount());
        $this->assertSame([['code' => 'a-code', 'client_id' => 'com.musicall.app']], $this->appleTokens()->exchanges);
        $this->assertSessionBodyFor('elodiedupre');
    }

    /** Apple sends no name after the first sign-in, and one sent anyway changes nothing. */
    public function test_a_linked_apple_account_signs_in_and_its_refresh_token_is_renewed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com', 'password' => null]);
        SocialAccountFactory::new()->create([
            'user' => $user,
            'provider' => SocialAccount::PROVIDER_APPLE,
            'providerId' => self::APPLE_ID,
            'refreshToken' => 'r.old-token',
            'refreshTokenClientId' => 'com.musicall.app',
        ]);
        $this->apple()->accept('a-token', $this->claims('alice@example.com'));
        $this->appleTokens()->willExchange('a-code', self::APPLE_ID, 'r.new-token');

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-code', 'given_name' => 'Someone', 'family_name' => 'Else']);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('alice_drums');
        $this->assertSame('r.new-token', $this->appleAccount()['refresh_token']);
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'alice_drums'])?->profile->displayName);
    }

    /** Apple failing the exchange must not cost the sign-in: the next one sends a fresh code. */
    public function test_a_code_apple_refuses_still_signs_in(): void
    {
        $this->apple()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-used-code']);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nouveau');
        $this->assertNull($this->appleAccount()['refresh_token']);
    }

    /** A token that would revoke someone else's Apple ID is never kept. */
    public function test_a_code_from_another_apple_id_is_not_kept(): void
    {
        $this->apple()->accept('a-token', $this->claims('nouveau@example.com'));
        $this->appleTokens()->willExchange('a-code', '009999.another.apple.id', 'r.someone-elses-token');

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-code']);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->appleAccount()['refresh_token']);
    }

    public function test_without_an_authorization_code_apple_is_not_asked(): void
    {
        $this->apple()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->appleTokens()->exchanges);
    }

    public function test_a_token_apple_does_not_vouch_for_is_refused(): void
    {
        // Covers a forged signature, an expired token and a wrong issuer alike: the real verifier
        // answers each of them the same way, see AppleIdentityTokenVerifierTest.
        $this->signIn(['identity_token' => 'not-an-apple-token']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** A genuine token issued to the website's Services ID is not good for the app. */
    public function test_a_token_for_another_client_is_refused(): void
    {
        $this->apple()->accept('a-token', $this->claims('alice@example.com'), audience: 'com.musicall.web');

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'not JSON' => ['identity_token=abc'];
        yield 'a JSON list' => ['["abc"]'];
        yield 'no token' => ['{"authorization_code": "a-code"}'];
        yield 'a token that is not a string' => ['{"identity_token": 42}'];
        yield 'an empty token' => ['{"identity_token": "  "}'];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_malformed_body_is_refused(string $body): void
    {
        $this->client->request('POST', '/api/native/oauth/apple', [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** Apple vouching for the token without its claims making an identity: a token, not an account. */
    public function test_an_identity_without_an_email_is_refused(): void
    {
        $this->apple()->accept('a-token', ['sub' => self::APPLE_ID]);

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    public function test_an_unverified_email_is_refused(): void
    {
        $this->apple()->accept('a-token', $this->claims('unverified@example.com', verified: false));

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-code']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'email_not_verified');
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'unverified@example.com']));
        $this->assertSame([], $this->appleTokens()->exchanges);
    }

    /** As with Google: the person signs in with their password and links the provider from there. */
    public function test_an_email_owned_by_an_existing_account_is_refused(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $this->apple()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn(['identity_token' => 'a-token', 'authorization_code' => 'a-code']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'email_exists');
        $this->assertSame([], self::getContainer()->get(SocialAccountRepository::class)->findAll());
        $this->assertSame([], $this->appleTokens()->exchanges);
    }

    public function test_a_suspended_account_is_refused_as_on_the_password_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'alice_drums',
            'email' => 'alice@example.com',
            'suspensionDatetime' => new \DateTimeImmutable('2026-10-01'),
        ]);
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_APPLE, 'providerId' => self::APPLE_ID]);
        $this->apple()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'account_suspended');
    }

    /** Apple could not be asked: the app says to try again rather than blaming the account. */
    public function test_apple_unreachable_is_a_503(): void
    {
        $this->apple()->becomeUnreachable();

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertRefused(Response::HTTP_SERVICE_UNAVAILABLE, 'apple_unavailable');
    }

    /**
     * The bucket NativeGoogleSignInTest spends too: alternating providers buys no extra attempts. Not
     * shown with a Google request first, because the kernel reboots between two requests of a test
     * and empties the limiter's pool.
     */
    #[TimeSensitive(SlidingWindowLimiter::class)]
    #[TimeSensitive(NativeOAuthAttemptLimiter::class)]
    public function test_attempts_are_limited_per_ip_in_the_bucket_google_shares(): void
    {
        ClockMock::withClockMock(strtotime('2026-10-30 10:00:00'));
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.native_oauth');
        $limiter->create('127.0.0.1')->consume(10);
        $this->apple()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Trop de tentatives de connexion échouées, veuillez réessayer dans 10 minutes.',
        ]);
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nouveau@example.com']));
    }

    /** @param array<string, mixed> $body */
    private function signIn(array $body): void
    {
        $this->client->jsonRequest('POST', '/api/native/oauth/apple', $body);
    }

    /** @return array<string, mixed> */
    private function claims(string $email, bool $verified = true): array
    {
        return [
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.musicall.app',
            'sub' => self::APPLE_ID,
            'email' => $email,
            'email_verified' => $verified ? 'true' : 'false',
        ];
    }

    /** @return array{provider: string, user: ?string, refresh_token: ?string, refresh_token_client_id: ?string} */
    private function appleAccount(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $account = $entityManager->getRepository(SocialAccount::class)->findOneBy(['providerId' => self::APPLE_ID]);
        $this->assertInstanceOf(SocialAccount::class, $account);

        return [
            'provider' => $account->provider,
            'user' => $account->user->id,
            'refresh_token' => $account->refreshToken,
            'refresh_token_client_id' => $account->refreshTokenClientId,
        ];
    }

    private function assertSessionBodyFor(string $username): void
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $keys = array_keys($body);
        sort($keys);
        $this->assertSame(['mercure_authorization', 'refresh_token', 'token'], $keys);
        $this->assertSame($username, JwtPayload::of($body['token'])['username']);
        // The native surface answers in the body only, never with a cookie.
        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    private function assertRefused(int $status, string $message): void
    {
        $this->assertResponseStatusCodeSame($status);
        $this->assertJsonEquals(['code' => $status, 'message' => $message]);
        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
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
