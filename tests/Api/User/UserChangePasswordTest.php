<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Covers POST /users/change_password (the authenticated ChangePassword resource).
 * NB: the similarly named UserPasswordChangeTest covers the *reset*-password flow.
 */
#[ResetDatabase]
class UserChangePasswordTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array LD_JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_change_password_success(): void
    {
        // asBaseUser() sets the password to UserFactory::DEFAULT_PASSWORD ('password').
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_password', [
            'oldPassword' => 'password',
            'newPassword' => 'new_password_123',
        ], self::LD_JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertNotSame(UserFactory::DEFAULT_PASSWORD, $user->password);
    }

    public function test_change_password_not_authenticated_returns_401(): void
    {
        $this->client->jsonRequest('POST', '/api/users/change_password', [
            'oldPassword' => 'password',
            'newPassword' => 'new_password_123',
        ], self::LD_JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_change_password_with_wrong_old_password_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_password', [
            'oldPassword' => 'wrong-current-password',
            'newPassword' => 'new_password_123',
        ], self::LD_JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/2d2a8bb4-ddc8-45e4-9b0f-8670d3a3e290',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => "old_password: L'ancien mot de passe est invalide",
            'status' => 422,
            'type' => '/validation_errors/2d2a8bb4-ddc8-45e4-9b0f-8670d3a3e290',
            'description' => "old_password: L'ancien mot de passe est invalide",
            'violations' => [
                [
                    'propertyPath' => 'old_password',
                    'message' => "L'ancien mot de passe est invalide",
                    'code' => '2d2a8bb4-ddc8-45e4-9b0f-8670d3a3e290',
                ],
            ],
        ]);

        // Password is unchanged.
        $this->assertSame(UserFactory::DEFAULT_PASSWORD, $user->password);
    }

    public function test_change_password_with_too_short_new_password_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_password', [
            'oldPassword' => 'password',
            'newPassword' => 'short',
        ], self::LD_JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'new_password: Le mot de passe doit contenir au moins 8 caractères.',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'new_password: Le mot de passe doit contenir au moins 8 caractères.',
            'violations' => [
                [
                    'propertyPath' => 'new_password',
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères.',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);

        $this->assertSame(UserFactory::DEFAULT_PASSWORD, $user->password);
    }
}
