<?php

declare(strict_types=1);

namespace App\Tests\Api\User\DeviceToken;

use App\Entity\User\DeviceToken;
use App\Repository\User\DeviceTokenRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class DeviceTokenPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string TOKEN = 'eKq3Hn0sR7aWm2Lp9xYtBc:APA91bGz8yQnU3vXk7JdTqW1mH5sPo2rLcA9fE4iN6bKjD0gV8hYuZtM3wSxQeR7pLnC1oB5aF2kJ9dH4gT6vU0yI8sW3eRqZxNmLpKjHgFdSaQwErTyUiOp';

    public function test_unauthenticated(): void
    {
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_registers_a_new_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'claire_drums', 'email' => 'claire.drums@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/DeviceToken',
            '@id' => '/api/user/device-tokens/' . self::TOKEN,
            '@type' => 'DeviceToken',
            'token' => self::TOKEN,
            'platform' => 'android',
        ]);

        $stored = $this->findToken(self::TOKEN);
        $this->assertSame($user->id, $stored->user->id);
        $this->assertSame(1, $this->countTokens());
    }

    public function test_registering_a_known_token_again_refreshes_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'marc_bass', 'email' => 'marc.bass@example.com']);
        DeviceTokenFactory::new()->create([
            'user' => $user,
            'token' => self::TOKEN,
            'lastSeenDatetime' => new \DateTimeImmutable('2026-01-15 10:00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/DeviceToken',
            '@id' => '/api/user/device-tokens/' . self::TOKEN,
            '@type' => 'DeviceToken',
            'token' => self::TOKEN,
            'platform' => 'android',
        ]);

        $stored = $this->findToken(self::TOKEN);
        $this->assertSame($user->id, $stored->user->id);
        $this->assertGreaterThan(new \DateTimeImmutable('2026-01-15 10:00:00'), $stored->lastSeenDatetime);
        $this->assertSame(1, $this->countTokens());
    }

    public function test_a_token_held_by_another_user_moves_to_the_caller(): void
    {
        $previousOwner = UserFactory::new()->asBaseUser()->create(['username' => 'lea_guitar', 'email' => 'lea.guitar@example.com']);
        $newOwner = UserFactory::new()->asBaseUser()->create(['username' => 'hugo_keys', 'email' => 'hugo.keys@example.com']);
        DeviceTokenFactory::new()->create(['user' => $previousOwner, 'token' => self::TOKEN]);

        $this->client->loginUser($newOwner);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/DeviceToken',
            '@id' => '/api/user/device-tokens/' . self::TOKEN,
            '@type' => 'DeviceToken',
            'token' => self::TOKEN,
            'platform' => 'android',
        ]);

        $this->assertSame($newOwner->id, $this->findToken(self::TOKEN)->user->id);
        $this->assertSame(1, $this->countTokens());
    }

    public function test_blank_token_and_unknown_platform(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'nina_vocals', 'email' => 'nina.vocals@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => '',
            'platform' => 'ios',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=' . NotBlank::IS_BLANK_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'token',
                    'message' => 'Veuillez fournir un jeton',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
                [
                    'propertyPath' => 'platform',
                    'message' => 'Plateforme inconnue',
                    'code' => Choice::NO_SUCH_CHOICE_ERROR,
                ],
            ],
            'detail' => "token: Veuillez fournir un jeton\nplatform: Plateforme inconnue",
            'description' => "token: Veuillez fournir un jeton\nplatform: Plateforme inconnue",
            'type' => '/validation_errors/0=' . NotBlank::IS_BLANK_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
        $this->assertSame(0, $this->countTokens());
    }

    public function test_token_with_characters_fcm_never_issues(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'paul_sax', 'email' => 'paul.sax@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => 'abc/../def?x=1',
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'token',
                    'message' => 'Le jeton est invalide',
                    'code' => Regex::REGEX_FAILED_ERROR,
                ],
            ],
            'detail' => 'token: Le jeton est invalide',
            'description' => 'token: Le jeton est invalide',
            'type' => '/validation_errors/' . Regex::REGEX_FAILED_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_missing_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'yann_ukulele', 'email' => 'yann.ukulele@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=' . NotBlank::IS_BLANK_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'token',
                    'message' => 'Veuillez fournir un jeton',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
                [
                    'propertyPath' => 'platform',
                    'message' => 'Plateforme inconnue',
                    'code' => Choice::NO_SUCH_CHOICE_ERROR,
                ],
            ],
            'detail' => "token: Veuillez fournir un jeton\nplatform: Plateforme inconnue",
            'description' => "token: Veuillez fournir un jeton\nplatform: Plateforme inconnue",
            'type' => '/validation_errors/0=' . NotBlank::IS_BLANK_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
        $this->assertSame(0, $this->countTokens());
    }

    public function test_registration_is_rate_limited(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'iris_mandolin', 'email' => 'iris.mandolin@example.com']);

        // Burn the whole 20/hour budget up front: loginUser() only authenticates one request.
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.device_token_register');
        $limiter->create('iris_mandolin')->consume(20);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/device-tokens', [
            'token' => self::TOKEN,
            'platform' => 'android',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
        $this->assertSame(0, $this->countTokens());
    }

    private function findToken(string $token): DeviceToken
    {
        // The upsert is raw SQL, so the identity map may hold a stale copy.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(DeviceTokenRepository::class)->findOneBy(['token' => $token]);
        $this->assertInstanceOf(DeviceToken::class, $stored);

        return $stored;
    }

    private function countTokens(): int
    {
        return self::getContainer()->get(DeviceTokenRepository::class)->count();
    }
}
