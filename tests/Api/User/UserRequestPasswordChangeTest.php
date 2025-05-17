<?php

namespace Api\User;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRequestPasswordChangeTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_request_password_change(): void
    {
        /** @var User $user */
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $this->assertNull($user->getResetRequestDatetime());

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertNotNull($user->getResetRequestDatetime());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'templateId', '2');
        $this->assertEmailTextBodyContains($email, 'Réinitialisation du mot de passe');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'base_user1@email.com');
    }

    public function test_request_password_change_with_email(): void
    {
        /** @var User $user */
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $this->assertNull($user->getResetRequestDatetime());

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user1@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertNotNull($user->getResetRequestDatetime());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'templateId', '2');
        $this->assertEmailTextBodyContains($email, 'Réinitialisation du mot de passe');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'base_user1@email.com');
    }

    public function test_request_password_change_with_account_not_found(): void
    {
        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'do_not_exist',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertEmailCount(0);
    }

    public function test_request_password_change_with_empty_login(): void
    {
        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'login',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ]
            ],
            'detail' => 'login: Cette valeur ne doit pas être vide.',
            'description' => 'login: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred'
        ]);

        $this->assertEmailCount(0);
    }
}