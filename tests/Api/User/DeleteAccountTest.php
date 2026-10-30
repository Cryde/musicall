<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\Publication;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\User\DeviceTokenRepository;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeAppleTokenClient;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class DeleteAccountTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_account_not_logged(): void
    {
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
            'password' => 'password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_delete_account_wrong_password(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'base_user_1',
            'email' => 'base_user1@email.com',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
            'password' => 'wrong_password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_d3a1f9b2-7c4e-4a8d-b5f6-9e2c1d7a3b08',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'password: Le mot de passe est invalide',
            'status' => 422,
            'type' => '/validation_errors/music_all_d3a1f9b2-7c4e-4a8d-b5f6-9e2c1d7a3b08',
            'description' => 'password: Le mot de passe est invalide',
            'violations' => [
                [
                    'propertyPath' => 'password',
                    'message' => 'Le mot de passe est invalide',
                    'code' => 'music_all_d3a1f9b2-7c4e-4a8d-b5f6-9e2c1d7a3b08',
                ],
            ],
        ]);
    }

    public function test_delete_account_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'base_user_1',
            'email' => 'base_user1@email.com',
        ]);

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'title' => 'My publication',
            'status' => 1,
            'type' => 1,
        ]);
        DeviceTokenFactory::new()->create(['user' => $user]);
        $someoneElsesDevice = DeviceTokenFactory::new()->create();
        UserBlockFactory::new()->create(['blocker' => $user]);
        UserBlockFactory::new()->create(['blocked' => $user]);
        $someoneElsesBlock = UserBlockFactory::new()->create();

        $realUser = $user;
        $userId = $realUser->id;

        $this->client->loginUser($realUser);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
            'password' => 'password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $deletedUser = $userRepo->find($userId);
        \assert($deletedUser instanceof User);

        // Deletion datetime is set
        $this->assertNotNull($deletedUser->deletionDatetime);
        $this->assertTrue($deletedUser->isDeleted());
        // Username and email are anonymized
        $this->assertSame('deleted_' . $userId, $deletedUser->username);
        $this->assertSame('deleted_' . $userId . '@deleted.local', $deletedUser->email);
        // Password is null
        $this->assertNull($deletedUser->password);
        // Roles are empty (getRoles() always adds ROLE_USER)
        $this->assertSame(['ROLE_USER'], $deletedUser->getRoles());
        // Profile picture is null
        $this->assertNull($deletedUser->profilePicture);
        // Social accounts are empty
        $this->assertCount(0, $deletedUser->socialAccounts);
        // Publication still exists (FK intact)
        /** @var Publication $realPublication */
        $realPublication = $publication;
        $this->assertSame('My publication', $realPublication->title);
        $this->assertSame($userId, $realPublication->author->id);
        // Their phones stop receiving pushes, nobody else's do
        $deviceTokens = static::getContainer()->get(DeviceTokenRepository::class);
        $this->assertSame([], $deviceTokens->findTokensForUser($deletedUser));
        $this->assertSame(1, $deviceTokens->count());
        $this->assertSame([$someoneElsesDevice->token], $deviceTokens->findTokensForUser($someoneElsesDevice->user));
        // Their blocks go, both ways; other people's stay
        $blocks = static::getContainer()->get(UserBlockRepository::class)->findAll();
        $this->assertSame([$someoneElsesBlock->id], array_map(static fn ($block) => $block->id, $blocks));
    }

    public function test_delete_account_oauth_user_no_password(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'oauth_user',
            'email' => 'oauth_user@email.com',
            'password' => null,
        ]);

        $realUser = $user;
        $userId = $realUser->id;

        $this->client->loginUser($realUser);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $deletedUser = $userRepo->find($userId);
        \assert($deletedUser instanceof User);

        $this->assertNotNull($deletedUser->deletionDatetime);
        $this->assertTrue($deletedUser->isDeleted());
        $this->assertSame('deleted_' . $userId, $deletedUser->username);
        $this->assertSame('deleted_' . $userId . '@deleted.local', $deletedUser->email);
        $this->assertNull($deletedUser->password);
    }

    /** Apple requires it (#1270), under the client id the token was issued to. */
    public function test_deleting_an_apple_account_revokes_its_refresh_token(): void
    {
        $user = $this->appleUser(refreshToken: 'r.apple-refresh-token');
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_GOOGLE]);

        $this->deleteAccountOf($user);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->reloaded($user)->isDeleted());
        $this->assertSame([['refresh_token' => 'r.apple-refresh-token', 'client_id' => 'com.musicall.app']], $this->appleTokens()->revocations);
    }

    /** Apple being down must not keep an account alive. */
    public function test_a_failed_revocation_still_deletes_the_account(): void
    {
        $user = $this->appleUser(refreshToken: 'r.apple-refresh-token');
        $this->appleTokens()->refuseRevocations();

        $this->deleteAccountOf($user);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $deletedUser = $this->reloaded($user);
        $this->assertTrue($deletedUser->isDeleted());
        $this->assertCount(0, $deletedUser->socialAccounts);
        $this->assertCount(1, $this->appleTokens()->revocations);
    }

    /** An Apple account whose code exchange failed holds no token, and a Google one never does. */
    public function test_an_account_without_an_apple_refresh_token_revokes_nothing(): void
    {
        $user = $this->appleUser(refreshToken: null);
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_GOOGLE]);

        $this->deleteAccountOf($user);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->reloaded($user)->isDeleted());
        $this->assertSame([], $this->appleTokens()->revocations);
    }

    private function appleUser(?string $refreshToken): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'apple_user', 'email' => 'x7k2@privaterelay.appleid.com', 'password' => null]);
        SocialAccountFactory::new()->create([
            'user' => $user,
            'provider' => SocialAccount::PROVIDER_APPLE,
            'providerId' => '001234.0123456789abcdef.1234',
            'refreshToken' => $refreshToken,
            'refreshTokenClientId' => $refreshToken !== null ? 'com.musicall.app' : null,
        ]);

        return $user;
    }

    private function deleteAccountOf(User $user): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
    }

    private function reloaded(User $user): User
    {
        $deletedUser = static::getContainer()->get(UserRepository::class)->find($user->id);
        \assert($deletedUser instanceof User);

        return $deletedUser;
    }

    private function appleTokens(): FakeAppleTokenClient
    {
        return static::getContainer()->get(FakeAppleTokenClient::class);
    }
}
