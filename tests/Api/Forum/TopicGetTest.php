<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TopicGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_topic(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'title' => 'Test Forum',
            'slug' => 'test-forum',
            'description' => 'Test forum description',
        ])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic Title',
            'slug' => 'test-topic-title',
            'author' => $author,
        ])->create();

        $topicId = $topic->getId();
        $forumId = $forum->getId();

        $this->client->request('GET', '/api/forums/topics/test-topic-title');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Topic',
            '@id' => '/api/forums/topics/test-topic-title',
            '@type' => 'Topic',
            'id' => $topicId,
            'title' => 'Test Topic Title',
            'slug' => 'test-topic-title',
            'forum' => [
                '@type' => 'Forum',
                'id' => $forumId,
                'title' => 'Test Forum',
                'slug' => 'test-forum',
                'description' => 'Test forum description',
            ],
        ]);
    }

    public function test_get_topic_not_found(): void
    {
        $this->client->request('GET', '/api/forums/topics/non-existent-topic');
        $this->assertResponseStatusCodeSame(404);
    }
}
