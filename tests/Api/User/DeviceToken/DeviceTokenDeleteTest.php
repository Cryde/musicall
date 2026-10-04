<?php

declare(strict_types=1);

namespace App\Tests\Api\User\DeviceToken;

use App\Repository\User\DeviceTokenRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class DeviceTokenDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string TOKEN = 'fPz7Rt2yVb9Nq4Kd1sXwLm:APA91bHc3vN8kQ2wE5rT9yU1iO4pA7sD0fG3hJ6kL9zX2cV5bN8mQ1wE4rT7yU0iO3pA6sD9fG2hJ5kL8zX1cV4bN7mQ0wE3rT6yU9iO2pA5sD8fG1hJ4kL7zX0cV3';

    public function test_unauthenticated(): void
    {
        $this->client->request('DELETE', '/api/user/device-tokens/' . rawurlencode(self::TOKEN));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_deletes_own_token_sent_url_encoded(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'sophie_cello', 'email' => 'sophie.cello@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::TOKEN]);

        $this->client->loginUser($user);
        // The app encodes with Uri.encodeComponent, so the colon arrives as %3A.
        $this->client->request('DELETE', '/api/user/device-tokens/' . rawurlencode(self::TOKEN));

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
        $this->assertSame(0, self::getContainer()->get(DeviceTokenRepository::class)->count());
    }

    public function test_another_users_token_is_not_found_and_kept(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'julien_trumpet', 'email' => 'julien.trumpet@example.com']);
        $caller = UserFactory::new()->asBaseUser()->create(['username' => 'emma_violin', 'email' => 'emma.violin@example.com']);
        DeviceTokenFactory::new()->create(['user' => $owner, 'token' => self::TOKEN]);

        $this->client->loginUser($caller);
        $this->client->request('DELETE', '/api/user/device-tokens/' . rawurlencode(self::TOKEN));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Appareil introuvable',
            'description' => 'Appareil introuvable',
        ]);
        $this->assertSame(1, self::getContainer()->get(DeviceTokenRepository::class)->count());
    }

    public function test_unknown_token_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'adam_flute', 'email' => 'adam.flute@example.com']);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/device-tokens/' . rawurlencode(self::TOKEN));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Appareil introuvable',
            'description' => 'Appareil introuvable',
        ]);
    }
}
