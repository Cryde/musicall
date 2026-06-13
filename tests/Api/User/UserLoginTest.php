<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserLoginTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_login(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertResponseHasCookie('jwt_hp');
        $this->assertResponseHasCookie('jwt_s');
        $this->assertResponseHasCookie('refresh_token');
    }

    public function test_login_unverified_user_with_bad_password_returns_generic_error(): void
    {
        // Anti-enumeration: a wrong password must look identical whether or not the
        // account exists / is verified. account_not_verified is only revealed AFTER
        // a correct password (see the test below), so a wrong-password probe cannot
        // detect an unverified account nor resolve its email.
        $user1 = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_unverified_user_with_correct_password_returns_account_not_verified(): void
    {
        // Only the account owner (correct password) learns the unverified status and
        // the email, so the frontend can route them to the verification flow.
        $user1 = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'account_not_verified',
            'email' => $user1->email,
        ]);
    }

    public function test_login_with_bad_password(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_with_non_existing_credentials(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => 'bad',
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_with_no_json(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'status' => 400,
            'detail' => 'Bad Request',
        ]);
    }
}
