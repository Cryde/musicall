<?php

declare(strict_types=1);

namespace App\Tests\Api\Native;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeGoogleIdTokenVerifier;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
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

    public function test_attempts_are_limited_per_ip(): void
    {
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.native_oauth');
        $limiter->create('127.0.0.1')->consume(10);
        $this->google()->accept('a-token', $this->claims('nouveau@example.com'));

        $this->signIn('a-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Plusieurs tentatives de connexion ont échoué, veuillez réessayer dans 1 minute.',
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

    private function google(): FakeGoogleIdTokenVerifier
    {
        return self::getContainer()->get(FakeGoogleIdTokenVerifier::class);
    }
}
