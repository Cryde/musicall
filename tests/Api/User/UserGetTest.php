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
            'id'              => $user1->getId(),
            'username'        => $user1->getUsername(),
            'email'           => $user1->getEmail(),
            'roles'           => ['ROLE_USER'],
            'profile_picture' => null,
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
            'id'              => $user1->getId(),
            'username'        => $user1->getUsername(),
            'email'           => $user1->getEmail(),
            'roles'           => ['ROLE_ADMIN', 'ROLE_USER'],
            'profile_picture' => null,
        ]);
    }


    public function test_get_item(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->request('GET', '/api/users/' . $user1->getId());
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/User',
            '@id' => '/api/users/' . $user1->getId(),
            '@type' => 'User',
            'id'              => $user1->getId(),
            'username'        => $user1->getUsername(),
            'profile_picture' => null,
        ]);
    }
}
