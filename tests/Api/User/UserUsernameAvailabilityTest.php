<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserUsernameAvailabilityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ResetDatabase;
    use Factories;

    public function test_username_available(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/users/username-availability/available_username', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UsernameAvailability',
            '@id' => '/api/users/username-availability/available_username',
            '@type' => 'UsernameAvailability',
            'username' => 'available_username',
            'available' => true,
        ]);
    }

    public function test_username_not_available(): void
    {
        UserFactory::new()->create(['username' => 'taken_username']);
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/users/username-availability/taken_username', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UsernameAvailability',
            '@id' => '/api/users/username-availability/taken_username',
            '@type' => 'UsernameAvailability',
            'username' => 'taken_username',
            'available' => false,
        ]);
    }

    public function test_username_availability_not_logged(): void
    {
        $this->client->jsonRequest('GET', '/api/users/username-availability/some_username', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
