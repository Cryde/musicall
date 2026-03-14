<?php

namespace App\Tests\Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_self_not_logged(): void
    {
        $this->client->request('GET', '/api/users/self');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_self(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/users/self');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id'              => $user1->id,
            'username'        => $user1->username,
            'email'           => $user1->email,
            'roles'           => ['ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => null,
            'has_password' => true,
        ]);
    }

    public function test_get_self_as_admin(): void
    {
        $user1 = UserFactory::new()->asAdminUser()->create()->_real();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/users/self');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id'              => $user1->id,
            'username'        => $user1->username,
            'email'           => $user1->email,
            'roles'           => ['ROLE_ADMIN', 'ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => null,
            'has_password' => true,
        ]);
    }


    public function test_get_item(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->request('GET', '/api/users/' . $user1->id);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/User',
            '@id' => '/api/users/' . $user1->id,
            '@type' => 'User',
            'id'              => $user1->id,
            'username'        => $user1->username,
            'profile_picture' => null,
            'deletion_datetime' => null,
        ]);
    }
}
