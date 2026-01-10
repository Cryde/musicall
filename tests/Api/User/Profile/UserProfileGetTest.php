<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserProfileGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_own_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'profileuser',
            'email' => 'profileuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('My bio');
        $profile->setLocation('Lyon, France');
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/profile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'My bio',
            'location' => 'Lyon, France',
            'is_public' => true,
        ]);
    }

    public function test_get_own_profile_with_private_setting(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'privateprofile',
            'email' => 'privateprofile@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Private bio');
        $profile->setIsPublic(false);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/profile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'Private bio',
            'is_public' => false,
        ]);
    }
}
