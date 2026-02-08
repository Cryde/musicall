<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TopicPostGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_collection_with_pagination(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'test-forum',
        ])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster1 = UserFactory::new(['username' => 'poster1'])->create();
        $poster2 = UserFactory::new(['username' => 'poster2'])->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        // Create posts in non-sequential order (older first in DB, newer second)
        $post2 = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster2,
            'content' => 'Second post content here',
            'creationDatetime' => new \DateTime('2024-01-10 15:00:00'),
            'updateDatetime' => null,
        ])->create();

        $post1 = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster1,
            'content' => 'First post content here',
            'creationDatetime' => new \DateTime('2024-01-05 10:00:00'),
            'updateDatetime' => null,
        ])->create();

        $post3 = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster1,
            'content' => 'Third post content here',
            'creationDatetime' => new \DateTime('2024-01-15 20:00:00'),
            'updateDatetime' => null,
        ])->create();

        $poster1Id = $poster1->getId();
        $poster2Id = $poster2->getId();

        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                // Ordered by creationDatetime ASC
                [
                    '@id' => '/api/topic_posts/' . $post1->getId(),
                    '@type' => 'TopicPost',
                    'id' => $post1->getId(),
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'First post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster1Id,
                        'username' => 'poster1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                ],
                [
                    '@id' => '/api/topic_posts/' . $post2->getId(),
                    '@type' => 'TopicPost',
                    'id' => $post2->getId(),
                    'creation_datetime' => '2024-01-10T15:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Second post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster2Id,
                        'username' => 'poster2',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                ],
                [
                    '@id' => '/api/topic_posts/' . $post3->getId(),
                    '@type' => 'TopicPost',
                    'id' => $post3->getId(),
                    'creation_datetime' => '2024-01-15T20:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Third post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster1Id,
                        'username' => 'poster1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_collection_empty_topic(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'test-forum',
        ])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();

        ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Empty Topic',
            'slug' => 'empty-topic',
            'author' => $author,
        ])->create();

        $this->client->request('GET', '/api/forums/topics/empty-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/empty-topic/posts',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_pagination_first_page(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'test-forum',
        ])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster = UserFactory::new(['username' => 'poster'])->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        // Create 15 posts (more than page size of 10)
        for ($i = 1; $i <= 15; $i++) {
            ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $poster,
                'content' => 'Post content ' . $i,
                'creationDatetime' => new \DateTime('2024-01-01 10:00:00 +' . $i . ' hours'),
                'updateDatetime' => null,
            ])->create();
        }

        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 15,
        ]);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(10, $response['member']);
    }

    public function test_get_collection_pagination_second_page(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'test-forum',
        ])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster = UserFactory::new(['username' => 'poster'])->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        // Create 15 posts (more than page size of 10)
        for ($i = 1; $i <= 15; $i++) {
            ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $poster,
                'content' => 'Post content ' . $i,
                'creationDatetime' => new \DateTime('2024-01-01 10:00:00 +' . $i . ' hours'),
                'updateDatetime' => null,
            ])->create();
        }

        $this->client->request('GET', '/api/forums/topics/test-topic/posts?page=2');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 15,
        ]);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(5, $response['member']);
    }
}
