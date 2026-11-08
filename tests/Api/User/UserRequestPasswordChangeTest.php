<?php

namespace App\Tests\Api\User;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserRequestPasswordChangeTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_request_password_change(): void
    {
        /** @var User $user */
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $this->assertNull($user->resetRequestDatetime);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertNotNull($user->resetRequestDatetime);
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
        $this->assertNull($user->resetRequestDatetime);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user1@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertNotNull($user->resetRequestDatetime);
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'templateId', '2');
        $this->assertEmailTextBodyContains($email, 'Réinitialisation du mot de passe');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'base_user1@email.com');
    }

    public function test_request_password_change_for_an_english_user_gets_the_english_template(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com', 'locale' => 'en']);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'templateId', '17');
        $this->assertEmailTextBodyContains($email, 'Réinitialisation du mot de passe');
        $this->assertEmailAddressContains($email, 'To', 'base_user1@email.com');
    }

    /** The reset link opens a new page, so the way back has to travel inside it (#1302). */
    public function test_the_reset_link_carries_a_safe_return_url(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
            'return_url' => '/band/1/agenda?view=month',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());

        $this->assertSame(
            'http://musicall.test/lost-password/' . $user->token . '?return_url=%2Fband%2F1%2Fagenda%3Fview%3Dmonth',
            $this->sentResetLink(),
        );
    }

    public function test_the_reset_link_drops_a_return_url_that_leaves_the_site(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
            'return_url' => '//evil.example/phish',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());

        $this->assertSame('http://musicall.test/lost-password/' . $user->token, $this->sentResetLink());
    }

    public function test_an_over_long_return_url_is_refused(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
            'return_url' => '/' . str_repeat('a', 2048),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'return_url',
                    'message' => 'Cette chaîne est trop longue. Elle doit contenir au maximum 2048 caractères.',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'return_url: Cette chaîne est trop longue. Elle doit contenir au maximum 2048 caractères.',
            'description' => 'return_url: Cette chaîne est trop longue. Elle doit contenir au maximum 2048 caractères.',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
        ]);

        $this->assertEmailCount(0);
    }

    private function sentResetLink(): mixed
    {
        $this->assertEmailCount(1);
        $params = $this->getMailerMessage()?->getHeaders()->get('params');
        $this->assertInstanceOf(ParameterizedHeader::class, $params);

        return $params->getParameters()['change_password_link'] ?? null;
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
