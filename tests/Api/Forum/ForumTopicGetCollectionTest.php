<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Entity\Forum\ForumTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumTopicGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_collection_with_pagination_and_ordering(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'test-forum',
        ])->create();

        $author1 = UserFactory::new(['username' => 'author1'])->create();
        $author2 = UserFactory::new(['username' => 'author2'])->create();
        $lastPostCreator = UserFactory::new(['username' => 'lastposter'])->create();

        // Create a pinned topic (should appear first)
        $pinnedTopic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Pinned Topic',
            'slug' => 'pinned-topic',
            'type' => ForumTopic::TYPE_TOPIC_PINNED,
            'isLocked' => false,
            'author' => $author1,
            'postNumber' => 5,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        // Create older regular topic
        $olderTopic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Older Topic',
            'slug' => 'older-topic',
            'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
            'isLocked' => true,
            'author' => $author2,
            'postNumber' => 10,
            'creationDatetime' => new \DateTime('2024-01-05 10:00:00'),
        ])->create();

        // Create newer regular topic with last post
        $newerTopic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Newer Topic',
            'slug' => 'newer-topic',
            'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
            'isLocked' => false,
            'author' => $author1,
            'postNumber' => 3,
            'creationDatetime' => new \DateTime('2024-01-10 10:00:00'),
        ])->create();

        $lastPost = ForumPostFactory::new([
            'topic' => $newerTopic,
            'creator' => $lastPostCreator,
            'creationDatetime' => new \DateTime('2024-01-15 15:30:00'),
        ])->create();

        $newerTopic->_real()->setLastPost($lastPost->_real());
        $newerTopic->_save();

        // Get IDs before request
        $author1Id = $author1->getId();
        $author2Id = $author2->getId();
        $lastPostCreatorId = $lastPostCreator->getId();
        $pinnedTopicId = $pinnedTopic->getId();
        $newerTopicId = $newerTopic->getId();
        $olderTopicId = $olderTopic->getId();
        $lastPostId = $lastPost->getId();

        $this->client->request('GET', '/api/forums/test-forum/topics');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumTopic',
            '@id' => '/api/forums/test-forum/topics',
            '@type' => 'Collection',
            'member' => [
                // Pinned topic first (type DESC)
                [
                    '@id' => '/api/forum_topics/pinned-topic',
                    '@type' => 'ForumTopic',
                    'id' => $pinnedTopicId,
                    'title' => 'Pinned Topic',
                    'slug' => 'pinned-topic',
                    'type' => ForumTopic::TYPE_TOPIC_PINNED,
                    'is_locked' => false,
                    'last_post' => null,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'author' => [
                        '@type' => 'User',
                        'id' => $author1Id,
                        'username' => 'author1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'post_number' => 5,
                ],
                // Newer topic second (creationDatetime DESC)
                [
                    '@id' => '/api/forum_topics/newer-topic',
                    '@type' => 'ForumTopic',
                    'id' => $newerTopicId,
                    'title' => 'Newer Topic',
                    'slug' => 'newer-topic',
                    'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
                    'is_locked' => false,
                    'last_post' => [
                        '@type' => 'ForumPost',
                        'id' => $lastPostId,
                        'creation_datetime' => '2024-01-15T15:30:00+00:00',
                        'creator' => [
                            '@type' => 'User',
                            'id' => $lastPostCreatorId,
                            'username' => 'lastposter',
                            'deletion_datetime' => null,
                            'profile_picture' => null,
                        ],
                    ],
                    'creation_datetime' => '2024-01-10T10:00:00+00:00',
                    'author' => [
                        '@type' => 'User',
                        'id' => $author1Id,
                        'username' => 'author1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'post_number' => 3,
                ],
                // Older topic last
                [
                    '@id' => '/api/forum_topics/older-topic',
                    '@type' => 'ForumTopic',
                    'id' => $olderTopicId,
                    'title' => 'Older Topic',
                    'slug' => 'older-topic',
                    'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
                    'is_locked' => true,
                    'last_post' => null,
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'author' => [
                        '@type' => 'User',
                        'id' => $author2Id,
                        'username' => 'author2',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'post_number' => 10,
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_collection_empty_forum(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => 'empty-forum',
        ])->create();

        $this->client->request('GET', '/api/forums/empty-forum/topics');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumTopic',
            '@id' => '/api/forums/empty-forum/topics',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }
}
