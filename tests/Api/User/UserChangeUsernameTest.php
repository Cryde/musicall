<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserChangeUsernameTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ResetDatabase;
    use Factories;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_change_username(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $oldUsername = $user->getUsername();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Verify username was changed in database
        $this->getEntityManager()->clear();
        $updatedUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        $this->assertSame('new_username', $updatedUser->getUsername());
        $this->assertNotSame($oldUsername, $updatedUser->getUsername());
        $this->assertNotNull($updatedUser->getUsernameChangedDatetime());
    }

    public function test_change_username_not_logged(): void
    {
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_change_username_already_taken(): void
    {
        UserFactory::new()->create(['username' => 'taken_username']);
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'taken_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce nom d\'utilisateur est déjà pris.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce nom d\'utilisateur est déjà pris.',
        ]);
    }

    public function test_change_username_same_as_current(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => $user->getUsername()], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le nouveau nom d\'utilisateur doit être différent de l\'actuel.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Le nouveau nom d\'utilisateur doit être différent de l\'actuel.',
        ]);
    }

    public function test_change_username_too_short(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'ab'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
            'detail' => 'new_username: Le nom d\'utilisateur doit au moins contenir 3 caractères',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'new_username: Le nom d\'utilisateur doit au moins contenir 3 caractères',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Le nom d\'utilisateur doit au moins contenir 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);
    }

    public function test_change_username_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => str_repeat('a', 41)], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'detail' => 'new_username: Le nom d\'utilisateur doit contenir maximum 40 caractères',
            'status' => 422,
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'description' => 'new_username: Le nom d\'utilisateur doit contenir maximum 40 caractères',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Le nom d\'utilisateur doit contenir maximum 40 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
        ]);
    }

    public function test_change_username_invalid_characters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'invalid@username!'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'detail' => 'new_username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'status' => 422,
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'description' => 'new_username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
        ]);
    }

    public function test_change_username_throttled(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'usernameChangedDatetime' => new \DateTimeImmutable('-10 days'),
        ])->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
            'title' => 'An error occurred',
            'detail' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
            'status' => 422,
            'type' => '/validation_errors/music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
            'description' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
                    'code' => 'music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
                ],
            ],
        ]);
    }

    public function test_change_username_after_cooldown(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'usernameChangedDatetime' => new \DateTimeImmutable('-31 days'),
        ])->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
