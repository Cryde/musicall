<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DeleteAccountTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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

        $this->client->loginUser($user->_real());
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

        $realUser = $user->_real();
        $userId = $realUser->getId();

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
        $this->assertNotNull($deletedUser->getDeletionDatetime());
        $this->assertTrue($deletedUser->isDeleted());
        // Username and email are anonymized
        $this->assertSame('deleted_' . $userId, $deletedUser->getUsername());
        $this->assertSame('deleted_' . $userId . '@deleted.local', $deletedUser->getEmail());
        // Password is null
        $this->assertNull($deletedUser->getPassword());
        // Roles are empty (getRoles() always adds ROLE_USER)
        $this->assertSame(['ROLE_USER'], $deletedUser->getRoles());
        // Profile picture is null
        $this->assertNull($deletedUser->getProfilePicture());
        // Social accounts are empty
        $this->assertCount(0, $deletedUser->getSocialAccounts());
        // Publication still exists (FK intact)
        /** @var Publication $realPublication */
        $realPublication = $publication->_real();
        $this->assertSame('My publication', $realPublication->getTitle());
        $this->assertSame($userId, $realPublication->getAuthor()->getId());
    }

    public function test_delete_account_oauth_user_no_password(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'oauth_user',
            'email' => 'oauth_user@email.com',
            'password' => null,
        ]);

        $realUser = $user->_real();
        $userId = $realUser->getId();

        $this->client->loginUser($realUser);
        $this->client->jsonRequest('POST', '/api/users/delete_account', [
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $deletedUser = $userRepo->find($userId);
        \assert($deletedUser instanceof User);

        $this->assertNotNull($deletedUser->getDeletionDatetime());
        $this->assertTrue($deletedUser->isDeleted());
        $this->assertSame('deleted_' . $userId, $deletedUser->getUsername());
        $this->assertSame('deleted_' . $userId . '@deleted.local', $deletedUser->getEmail());
        $this->assertNull($deletedUser->getPassword());
    }
}
