<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Entity\Forum\ForumTopic;
use App\Repository\Forum\ForumTopicRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class ForumTopicPinTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array UNAUTH_BODY = [
        'code' => 401,
        'message' => 'JWT Token not found',
    ];

    private const array NOT_FOUND_BODY = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/404',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Topic not found',
        'description' => 'Topic not found',
        'status' => 404,
        'type' => '/errors/404',
    ];

    private const array FORBIDDEN_BODY = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/403',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Access Denied.',
        'description' => 'Access Denied.',
        'status' => 403,
        'type' => '/errors/403',
    ];

    public function test_pin_unauthenticated_returns_401(): void
    {
        $this->createTopic();

        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(self::UNAUTH_BODY);
    }

    public function test_pin_unknown_slug_returns_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $this->client->loginUser($admin);

        $this->client->jsonRequest('POST', '/api/forums/topics/does-not-exist/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND_BODY);
    }

    public function test_pin_by_base_user_returns_403(): void
    {
        $topic = $this->createTopic();
        $user = UserFactory::new(['username' => 'other_user', 'email' => 'other@email.com'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals(self::FORBIDDEN_BODY);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertSame(ForumTopic::TYPE_TOPIC_DEFAULT, $repository->find($topic->id)->type);
    }

    public function test_pin_by_author_returns_403(): void
    {
        $topic = $this->createTopic();

        $this->client->loginUser($topic->author);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals(self::FORBIDDEN_BODY);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertSame(ForumTopic::TYPE_TOPIC_DEFAULT, $repository->find($topic->id)->type);
    }

    public function test_pin_by_admin_succeeds(): void
    {
        $topic = $this->createTopic();
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertSame(ForumTopic::TYPE_TOPIC_PINNED, $repository->find($topic->id)->type);
    }

    public function test_pin_already_pinned_is_idempotent(): void
    {
        $topic = $this->createTopic(['type' => ForumTopic::TYPE_TOPIC_PINNED]);
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/pin', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertSame(ForumTopic::TYPE_TOPIC_PINNED, $repository->find($topic->id)->type);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTopic(array $overrides = []): \App\Entity\Forum\ForumTopic
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $author = UserFactory::new(['username' => 'topic_author', 'email' => 'topic_author@email.com'])->create();

        return ForumTopicFactory::new(array_merge([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ], $overrides))->create();
    }
}
