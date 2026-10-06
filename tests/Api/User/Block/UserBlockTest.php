<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Block;

use App\Repository\User\Relation\UserBlockRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserBlockTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_unauthenticated(): void
    {
        $this->client->request('GET', '/api/user/blocks');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_unauthenticated_block(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);

        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $user->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_unauthenticated_unblock(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);

        $this->client->request('DELETE', '/api/user/blocks/' . $user->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_block_a_user(): void
    {
        $blocker = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $blocked = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);

        $this->client->loginUser($blocker);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $blocked->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $block = $this->findBlock($blocker->id, $blocked->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBlock',
            '@id' => '/api/user/blocks/' . $blocked->id,
            '@type' => 'UserBlock',
            'user_id' => $blocked->id,
            'username' => 'bob_bass',
            'display_name' => 'bob_bass',
            'profile_picture_url' => null,
            'creation_datetime' => $block->creationDatetime->format('c'),
        ]);
    }

    public function test_blocking_again_keeps_the_original_block(): void
    {
        $blocker = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $blocked = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        UserBlockFactory::new()->create([
            'blocker' => $blocker,
            'blocked' => $blocked,
            'creationDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00'),
        ]);

        $this->client->loginUser($blocker);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $blocked->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBlock',
            '@id' => '/api/user/blocks/' . $blocked->id,
            '@type' => 'UserBlock',
            'user_id' => $blocked->id,
            'username' => 'bob_bass',
            'display_name' => 'bob_bass',
            'profile_picture_url' => null,
            'creation_datetime' => '2026-09-01T10:00:00+00:00',
        ]);
        $this->assertSame(1, self::getContainer()->get(UserBlockRepository::class)->count());
    }

    public function test_blocking_oneself_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $user->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 422,
            'type' => '/errors/422',
            'detail' => 'Vous ne pouvez pas vous bloquer vous-même',
            'description' => 'Vous ne pouvez pas vous bloquer vous-même',
        ]);
        $this->assertSame(0, self::getContainer()->get(UserBlockRepository::class)->count());
    }

    public function test_blocking_an_unknown_or_closed_account_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $closed = UserFactory::new()->asBaseUser()->create([
            'username' => 'gone_user',
            'email' => 'gone.user@example.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-06-01'),
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $closed->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Utilisateur introuvable',
            'description' => 'Utilisateur introuvable',
        ]);
    }

    public function test_blocking_a_malformed_id_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => 'not-a-uuid'], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Utilisateur introuvable',
            'description' => 'Utilisateur introuvable',
        ]);
    }

    public function test_missing_user_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/blocks', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'user_id',
                    'message' => 'Veuillez préciser l\'utilisateur à bloquer',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'user_id: Veuillez préciser l\'utilisateur à bloquer',
            'description' => 'user_id: Veuillez préciser l\'utilisateur à bloquer',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_blocking_is_rate_limited(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);

        // Burn the whole budget up front: loginUser() only authenticates one request.
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.user_block');
        $limiter->create('alice_drums')->consume(30);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/blocks', ['user_id' => $other->id], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

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
        $this->assertSame(0, self::getContainer()->get(UserBlockRepository::class)->count());
    }

    public function test_list_shows_only_the_callers_blocks_newest_first(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $older = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        $newer = UserFactory::new()->asBaseUser()->create(['username' => 'carl_keys', 'email' => 'carl.keys@example.com']);
        UserBlockFactory::new()->create(['blocker' => $user, 'blocked' => $older, 'creationDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00')]);
        UserBlockFactory::new()->create(['blocker' => $user, 'blocked' => $newer, 'creationDatetime' => new \DateTimeImmutable('2026-09-02 10:00:00')]);
        // Somebody blocking the caller is not theirs to see.
        UserBlockFactory::new()->create(['blocker' => $older, 'blocked' => $user]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/blocks');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserBlock',
            '@id' => '/api/user/blocks',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user/blocks/' . $newer->id,
                    '@type' => 'UserBlock',
                    'user_id' => $newer->id,
                    'username' => 'carl_keys',
                    'display_name' => 'carl_keys',
                    'profile_picture_url' => null,
                    'creation_datetime' => '2026-09-02T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/user/blocks/' . $older->id,
                    '@type' => 'UserBlock',
                    'user_id' => $older->id,
                    'username' => 'bob_bass',
                    'display_name' => 'bob_bass',
                    'profile_picture_url' => null,
                    'creation_datetime' => '2026-09-01T10:00:00+00:00',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_unblock(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $blocked = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        UserBlockFactory::new()->create(['blocker' => $user, 'blocked' => $blocked]);
        // The other direction is the other person's block and survives.
        UserBlockFactory::new()->create(['blocker' => $blocked, 'blocked' => $user]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/blocks/' . $blocked->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
        $remaining = self::getContainer()->get(UserBlockRepository::class)->findAll();
        $this->assertCount(1, $remaining);
        $this->assertSame($blocked->id, $remaining[0]->blocker->id);
    }

    public function test_unblocking_someone_not_blocked_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $user]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/blocks/' . $other->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Cet utilisateur n\'est pas bloqué',
            'description' => 'Cet utilisateur n\'est pas bloqué',
        ]);
        $this->assertSame(1, self::getContainer()->get(UserBlockRepository::class)->count());
    }

    public function test_unblocking_a_malformed_id_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/blocks/not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Cet utilisateur n\'est pas bloqué',
            'description' => 'Cet utilisateur n\'est pas bloqué',
        ]);
    }

    private function findBlock(string $blockerId, string $blockedId): \App\Entity\User\Relation\UserBlock
    {
        $block = self::getContainer()->get(UserBlockRepository::class)->findOneBy(['blocker' => $blockerId, 'blocked' => $blockedId]);
        $this->assertNotNull($block);

        return $block;
    }
}
