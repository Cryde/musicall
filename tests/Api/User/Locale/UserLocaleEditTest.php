<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Locale;

use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserLocaleEditTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->jsonRequest('PATCH', '/api/users/self/locale', ['locale' => 'fr'], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_change_locale(): void
    {
        // A locale stored before it was disabled, so the change to `fr` is observable.
        $user = UserFactory::new()->asBaseUser()->create(['locale' => 'en']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/users/self/locale', ['locale' => 'fr'], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserLocaleEdit',
            '@id' => '/api/users/self/locale',
            '@type' => 'UserLocaleEdit',
            'locale' => 'fr',
        ]);
        $reloaded = self::getContainer()->get(UserRepository::class)->find($user->id);
        $this->assertSame('fr', $reloaded->locale);
    }

    public function test_change_to_a_locale_that_is_not_enabled(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/users/self/locale', ['locale' => 'en'], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'locale',
                    'message' => 'Cette langue n\'est pas disponible',
                    'code' => 'music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
                ],
            ],
            'detail' => 'locale: Cette langue n\'est pas disponible',
            'type' => '/validation_errors/music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
            'title' => 'An error occurred',
            'description' => 'locale: Cette langue n\'est pas disponible',
        ]);
        $reloaded = self::getContainer()->get(UserRepository::class)->find($user->id);
        $this->assertSame('fr', $reloaded->locale);
    }

    public function test_change_without_a_locale(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/users/self/locale', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: '{}');

        // A merge patch without the field keeps the stored value, which is valid.
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserLocaleEdit',
            '@id' => '/api/users/self/locale',
            '@type' => 'UserLocaleEdit',
            'locale' => 'fr',
        ]);
    }

    public function test_change_to_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/users/self/locale', ['locale' => null], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'locale',
                    'message' => 'Cette valeur doit être de type string.',
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => 'locale: Cette valeur doit être de type string.',
            'description' => 'locale: Cette valeur doit être de type string.',
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
        ]);
    }
}
