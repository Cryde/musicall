<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Mercure\MercureTopic;
use App\Repository\SocialAccountRepository;
use App\Repository\UserRepository;
use App\Security\Native\NativeOAuthAttemptLimiter;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeAppleIdentityTokenVerifier;
use App\Tests\Double\FakeAppleTokenClient;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
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

    public function test_a_sign_up_from_the_ios_app_records_it_with_its_version(): void
    {
        $this->apple()->accept('a-token', $this->claims('x7k2@privaterelay.appleid.com'));

        $this->client->jsonRequest('POST', '/api/native/oauth/apple', ['identity_token' => 'a-token', 'given_name' => 'Élodie', 'family_name' => 'Dupré'], [
            'HTTP_X_CLIENT_PLATFORM' => 'ios',
            'HTTP_X_CLIENT_VERSION' => '1.13.0+42',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('elodiedupre');
        $this->assertSame(['ios', 'apple', '1.13.0+42'], $this->registrationOf('elodiedupre'));
    }

    /** The native route is the app for sure, but which one only the header can say. */
    public function test_a_sign_up_without_the_platform_header_is_an_unknown_app(): void
    {
        $this->apple()->accept('a-token', $this->claims('x7k2@privaterelay.appleid.com'));

        $this->client->jsonRequest('POST', '/api/native/oauth/apple', ['identity_token' => 'a-token', 'given_name' => 'Élodie', 'family_name' => 'Dupré'], [
            'HTTP_X_CLIENT_VERSION' => '1.13.0',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('elodiedupre');
        $this->assertSame(['unknown_app', 'apple', '1.13.0'], $this->registrationOf('elodiedupre'));
    }

    /** `web` is not something the app can claim to be on its native route: dropped, and the bad version with it. */
    public function test_a_sign_up_with_an_invalid_platform_header_is_an_unknown_app(): void
    {
        $this->apple()->accept('a-token', $this->claims('x7k2@privaterelay.appleid.com'));

        $this->client->jsonRequest('POST', '/api/native/oauth/apple', ['identity_token' => 'a-token', 'given_name' => 'Élodie', 'family_name' => 'Dupré'], [
            'HTTP_X_CLIENT_PLATFORM' => 'web',
            'HTTP_X_CLIENT_VERSION' => 'latest',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('elodiedupre');
        $this->assertSame(['unknown_app', 'apple', null], $this->registrationOf('elodiedupre'));
    }

    /** Invited before the account existed: signing up with Apple joins the band, as a password sign-up does. */
    public function test_a_first_sign_in_joins_the_band_its_email_was_invited_to(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'band_admin', 'email' => 'admin@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin]);
        $invitation = BandSpaceInvitationFactory::new()->create(['bandSpace' => $bandSpace, 'invitedBy' => $admin, 'email' => 'nouveau@example.com']);
        $this->apple()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn(['identity_token' => 'a-token']);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nouveau');
        $newMember = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'nouveau']);
        $this->assertInstanceOf(User::class, $newMember);
        $this->assertSame([
            'members' => [['band_admin', 'admin', 'active'], ['nouveau', 'user', 'active']],
            'invitation' => ['accepted', 'nouveau'],
        ], $this->bandOf((string) $invitation->id));
        $this->assertEqualsCanonicalizing([
            MercureTopic::userNotifications((string) $admin->id),
            MercureTopic::userNotifications((string) $newMember->id),
        ], $this->changeSignalTopics(), 'The band hears that its members changed, once');
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

    /** @return array{string, string, ?string} the platform, method and app version stored for the account */
    private function registrationOf(string $username): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return [$user->registrationPlatform->value, $user->registrationMethod->value, $user->registrationAppVersion];
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

    /**
     * The invitation's band read back from the database: its members, and what became of the invitation.
     *
     * @return array{members: list<array{string, string, string}>, invitation: array{string, ?string}}
     */
    private function bandOf(string $invitationId): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $invitation = $entityManager->find(BandSpaceInvitation::class, $invitationId);
        $this->assertInstanceOf(BandSpaceInvitation::class, $invitation);
        $members = array_map(
            static fn (BandSpaceMembership $membership): array => [$membership->user->username, $membership->role->value, $membership->status->value],
            $entityManager->getRepository(BandSpaceMembership::class)->findBy(['bandSpace' => $invitation->bandSpace]),
        );
        sort($members);

        return ['members' => $members, 'invitation' => [$invitation->status->value, $invitation->existingUser?->username]];
    }

    /** @return list<string> */
    private function changeSignalTopics(): array
    {
        $updates = array_filter(
            self::getContainer()->get(RecordingHub::class)->updates,
            static fn (Update $update): bool => str_contains($update->getData(), '"band_space_changed"'),
        );

        return array_merge(...array_map(static fn (Update $update): array => $update->getTopics(), array_values($updates)));
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
