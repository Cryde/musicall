<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Repository\Forum\ForumTopicRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class ForumTopicResolveTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_resolve_unauthenticated_returns_401(): void
    {
        $this->createTopic();

        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_resolve_unknown_slug_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/forums/topics/does-not-exist/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_resolve_by_random_user_returns_403(): void
    {
        $topic = $this->createTopic();
        $otherUser = UserFactory::new(['username' => 'other_user', 'email' => 'other@email.com'])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertFalse($repository->find($topic->id)->isResolved);
    }

    public function test_resolve_by_author_succeeds(): void
    {
        $topic = $this->createTopic();

        $this->client->loginUser($topic->author);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertTrue($repository->find($topic->id)->isResolved);
    }

    public function test_resolve_by_admin_succeeds(): void
    {
        $topic = $this->createTopic();
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertTrue($repository->find($topic->id)->isResolved);
    }

    public function test_resolve_already_resolved_is_idempotent(): void
    {
        $topic = $this->createTopic(['isResolved' => true]);

        $this->client->loginUser($topic->author);
        $this->client->jsonRequest('POST', '/api/forums/topics/test-topic/resolve', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $repository = static::getContainer()->get(ForumTopicRepository::class);
        $this->assertTrue($repository->find($topic->id)->isResolved);
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
