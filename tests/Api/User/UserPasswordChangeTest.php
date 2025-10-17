<?php

namespace App\Tests\Api\User;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserPasswordChangeTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_password_change(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com', 'token' => 'token-abc', 'resetRequestDatetime' => new \DateTime('2 minutes ago')])
            ->_disableAutoRefresh();

        $this->assertSame(UserFactory::DEFAULT_PASSWORD, $user->getPassword());

        $this->client->jsonRequest('POST', '/api/users/reset-password/token-abc', [
            'password' => 'new_password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertNotSame(UserFactory::DEFAULT_PASSWORD, $user->getPassword());
        $this->assertNull($user->getResetRequestDatetime());
        $this->assertNull($user->getToken());
    }

    public function test_password_change_with_too_old_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com', 'token' => 'token-abc', 'resetRequestDatetime' => new \DateTime('17 minutes ago')])
            ->_disableAutoRefresh();

        $this->assertSame(UserFactory::DEFAULT_PASSWORD, $user->getPassword());

        $this->client->jsonRequest('POST', '/api/users/reset-password/token-abc', [
            'password' => 'new_password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'status' => 404,
            'detail' => 'Le token n\'est pas valide ou a expiré.',
            'description' => 'Le token n\'est pas valide ou a expiré.',
            'type' => '/errors/404',
            'title' => 'An error occurred',
        ]);

        $this->assertSame(UserFactory::DEFAULT_PASSWORD, $user->getPassword());
        $this->assertNotNull($user->getResetRequestDatetime());
        $this->assertNotNull($user->getToken());
    }

    public function test_password_change_with_weak_password(): void
    {
        $this->client->jsonRequest('POST', '/api/users/reset-password/not-found', [
            'password' => 'pas',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'password',
                    'message' => 'Minimum 6 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ]
            ],
            'detail' => 'password: Minimum 6 caractères',
            'description' => 'password: Minimum 6 caractères',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
        ]);
    }

    public function test_password_change_with_invalid_token(): void
    {
        $this->client->jsonRequest('POST', '/api/users/reset-password/not-found', [
            'password' => 'password',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'status' => 404,
            'detail' => 'Le token n\'est pas valide ou a expiré.',
            'description' => 'Le token n\'est pas valide ou a expiré.',
            'type' => '/errors/404',
            'title' => 'An error occurred',
        ]);
    }
}
