<?php

namespace App\Tests\Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserLoginTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_login(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->getUsername(),
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertResponseHasCookie('jwt_hp');
        $this->assertResponseHasCookie('jwt_s');
        $this->assertResponseHasCookie('refresh_token');
    }

    public function test_login_with_user_not_confirmed(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null])->_real();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->getUsername(),
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Vous devez confirmer votre compte pour pouvoir vous connecter',
        ]);
    }

    public function test_login_with_bad_password(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->getUsername(),
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
