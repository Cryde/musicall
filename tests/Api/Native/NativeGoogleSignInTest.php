<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Mercure\MercureTopic;
use App\Repository\UserRepository;
use App\Security\Native\NativeOAuthAttemptLimiter;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeGoogleIdTokenVerifier;
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
 * Google sign-in for the app (#1133): an ID token in, the native session body out. Google itself is
 * a double here; GoogleIdTokenVerifierTest covers what the real check refuses.
 */
#[ResetDatabase]
class NativeGoogleSignInTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string GOOGLE_ID = '109876543210987654321';

    public function test_a_linked_google_account_signs_in(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com', 'password' => null]);
        SocialAccountFactory::new()->create(['user' => $user, 'providerId' => self::GOOGLE_ID, 'email' => 'alice@example.com']);
        $this->google()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('alice_drums');
    }

    public function test_an_unknown_email_creates_a_confirmed_account(): void
    {
        $this->google()->accept('a-token', $this->claims('nouveau@example.com', name: 'Nina Vocals'));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nouveau@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotNull($user->confirmationDatetime);
        $this->assertNull($user->password);
        $socialAccounts = self::getContainer()->get(EntityManagerInterface::class)->getRepository(SocialAccount::class)->findBy(['user' => $user]);
        $this->assertCount(1, $socialAccounts);
        $this->assertSame(self::GOOGLE_ID, $socialAccounts[0]->providerId);
        $this->assertSessionBodyFor($user->username);
    }

    public function test_a_sign_up_from_the_android_app_records_it_with_its_version(): void
    {
        $this->google()->accept('a-token', $this->claims('nouveau@example.com', name: 'Nina'));

        $this->client->jsonRequest('POST', '/api/native/oauth/google', ['id_token' => 'a-token'], [
            'HTTP_X_CLIENT_PLATFORM' => 'android',
            'HTTP_X_CLIENT_VERSION' => '1.13.0',
            // The app sends its own language, which the account is created in (#1371).
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nina');
        $this->assertSame(['android', 'google', '1.13.0', 'en'], $this->registrationOf('nina'));
    }

    /** The native route is the app for sure, but which one only the header can say. */
    public function test_a_sign_up_without_the_platform_header_is_an_unknown_app(): void
    {
        $this->google()->accept('a-token', $this->claims('nouveau@example.com', name: 'Nina'));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nina');
        $this->assertSame(['unknown_app', 'google', null, 'fr'], $this->registrationOf('nina'));
    }

    /** A platform the app cannot be is dropped like a missing one; the version stays, since the route is the app. */
    public function test_a_sign_up_with_an_invalid_platform_header_is_an_unknown_app(): void
    {
        $this->google()->accept('a-token', $this->claims('nouveau@example.com', name: 'Nina'));

        $this->client->jsonRequest('POST', '/api/native/oauth/google', ['id_token' => 'a-token'], [
            'HTTP_X_CLIENT_PLATFORM' => 'windows',
            'HTTP_X_CLIENT_VERSION' => '1.13.0',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nina');
        $this->assertSame(['unknown_app', 'google', '1.13.0', 'fr'], $this->registrationOf('nina'));
    }

    /** Invited before the account existed: signing up with Google joins the band, as a password sign-up does. */
    public function test_a_first_sign_in_joins_the_band_its_email_was_invited_to(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'band_admin', 'email' => 'admin@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin]);
        $invitation = BandSpaceInvitationFactory::new()->create(['bandSpace' => $bandSpace, 'invitedBy' => $admin, 'email' => 'nouveau@example.com']);
        $this->google()->accept('a-token', $this->claims('nouveau@example.com', name: 'Nina'));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('nina');
        $newMember = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'nina']);
        $this->assertInstanceOf(User::class, $newMember);
        $this->assertSame([
            'members' => [['band_admin', 'admin', 'active'], ['nina', 'user', 'active']],
            'invitation' => ['accepted', 'nina'],
        ], $this->bandOf((string) $invitation->id));
        $this->assertEqualsCanonicalizing([
            MercureTopic::userNotifications((string) $admin->id),
            MercureTopic::userNotifications((string) $newMember->id),
        ], $this->changeSignalTopics(), 'The band hears that its members changed');
    }

    /** Only a new account joins on its own: a returning one answers the invitation like anybody else. */
    public function test_a_returning_sign_in_leaves_an_invitation_for_the_person_to_answer(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com', 'password' => null]);
        SocialAccountFactory::new()->create(['user' => $user, 'providerId' => self::GOOGLE_ID, 'email' => 'alice@example.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'band_admin', 'email' => 'admin@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin]);
        $invitation = BandSpaceInvitationFactory::new()->create([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'alice@example.com',
            'existingUser' => $user,
        ]);
        $this->google()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('alice_drums');
        $this->assertSame([
            'members' => [['band_admin', 'admin', 'active']],
            'invitation' => ['pending', 'alice_drums'],
        ], $this->bandOf((string) $invitation->id));
    }

    public function test_a_token_google_does_not_vouch_for_is_refused(): void
    {
        // Covers a forged signature, an expired token and another client's audience alike: the real
        // verifier answers each of them the same way, see GoogleIdTokenVerifierTest.
        $this->signIn('not-a-google-token');

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    public function test_a_request_without_a_token_is_refused(): void
    {
        $this->client->jsonRequest('POST', '/api/native/oauth/google', []);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'not JSON' => ['id_token=abc'];
        yield 'a JSON list' => ['["abc"]'];
        yield 'a token that is not a string' => ['{"id_token": 42}'];
        yield 'an empty token' => ['{"id_token": ""}'];
    }

    #[DataProvider('malformedBodies')]
    public function test_a_malformed_body_is_refused(string $body): void
    {
        $this->client->request('POST', '/api/native/oauth/google', [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** Google vouching for the token without its claims making an identity: a token, not an account. */
    public function test_an_identity_without_an_id_is_refused(): void
    {
        $this->google()->accept('a-token', ['sub' => 42, 'email' => 'alice@example.com', 'email_verified' => true]);

        $this->signIn('a-token');

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'invalid_id_token');
    }

    /** The account is the Google `sub` it is linked to; the email only matters when creating one. */
    public function test_a_linked_account_signs_in_even_with_an_unverified_email(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        SocialAccountFactory::new()->create(['user' => $user, 'providerId' => self::GOOGLE_ID]);
        $this->google()->accept('a-token', $this->claims('alice@example.com', verified: false));

        $this->signIn('a-token');

        $this->assertResponseIsSuccessful();
        $this->assertSessionBodyFor('alice_drums');
    }

    public function test_an_unverified_email_is_refused(): void
    {
        $this->google()->accept('a-token', $this->claims('unverified@example.com', verified: false));

        $this->signIn('a-token');

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'email_not_verified');
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'unverified@example.com']));
    }

    public function test_an_email_owned_by_a_password_account_is_refused(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $this->google()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn('a-token');

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'email_exists');
    }

    public function test_a_suspended_account_is_refused_as_on_the_password_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'alice_drums',
            'email' => 'alice@example.com',
            'suspensionDatetime' => new \DateTimeImmutable('2026-10-01'),
        ]);
        SocialAccountFactory::new()->create(['user' => $user, 'providerId' => self::GOOGLE_ID]);
        $this->google()->accept('a-token', $this->claims('alice@example.com'));

        $this->signIn('a-token');

        $this->assertRefused(Response::HTTP_UNAUTHORIZED, 'account_suspended');
    }

    /** Google could not be asked: the app says to try again rather than blaming the account. */
    public function test_google_unreachable_is_a_503(): void
    {
        $this->google()->becomeUnreachable();

        $this->signIn('a-token');

        $this->assertRefused(Response::HTTP_SERVICE_UNAVAILABLE, 'google_unavailable');
    }

    /** The wait is rounded up from a sub-second window start, so the clock sits on a whole second. */
    #[TimeSensitive(SlidingWindowLimiter::class)]
    #[TimeSensitive(NativeOAuthAttemptLimiter::class)]
    public function test_attempts_are_limited_per_ip(): void
    {
        ClockMock::withClockMock(strtotime('2026-10-30 10:00:00'));
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.native_oauth');
        $limiter->create('127.0.0.1')->consume(10);
        $this->google()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn('a-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Trop de tentatives de connexion échouées, veuillez réessayer dans 10 minutes.',
        ]);
        $this->assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nouveau@example.com']));
    }

    private function signIn(string $idToken): void
    {
        $this->client->jsonRequest('POST', '/api/native/oauth/google', ['id_token' => $idToken]);
    }

    /** @return array<string, mixed> */
    private function claims(string $email, bool $verified = true, string $name = 'Alice'): array
    {
        return ['sub' => self::GOOGLE_ID, 'email' => $email, 'email_verified' => $verified, 'name' => $name];
    }

    /** @return array{string, string, ?string, string} the platform, method, app version and language stored for the account */
    private function registrationOf(string $username): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return [$user->registrationPlatform->value, $user->registrationMethod->value, $user->registrationAppVersion, $user->registrationLocale];
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

    private function google(): FakeGoogleIdTokenVerifier
    {
        return self::getContainer()->get(FakeGoogleIdTokenVerifier::class);
    }
}
