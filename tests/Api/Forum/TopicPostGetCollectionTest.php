<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TopicPostGetCollectionTest extends ApiTestCase
{
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

        $poster1Id = $poster1->id;
        $poster2Id = $poster2->id;

        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                // Ordered by creationDatetime ASC
                [
                    '@id' => '/api/topic_posts/' . $post1->id,
                    '@type' => 'TopicPost',
                    'id' => $post1->id,
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'First post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster1Id,
                        'username' => 'poster1',
                        'display_name' => 'poster1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
                [
                    '@id' => '/api/topic_posts/' . $post2->id,
                    '@type' => 'TopicPost',
                    'id' => $post2->id,
                    'creation_datetime' => '2024-01-10T15:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Second post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster2Id,
                        'username' => 'poster2',
                        'display_name' => 'poster2',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
                [
                    '@id' => '/api/topic_posts/' . $post3->id,
                    '@type' => 'TopicPost',
                    'id' => $post3->id,
                    'creation_datetime' => '2024-01-15T20:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Third post content here',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster1Id,
                        'username' => 'poster1',
                        'display_name' => 'poster1',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
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
        $posts = [];
        for ($i = 1; $i <= 15; $i++) {
            $posts[$i] = ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $poster,
                'content' => 'Post content ' . $i,
                'creationDatetime' => new \DateTime('2024-01-01 10:00:00 +' . $i . ' hours'),
                'updateDatetime' => null,
            ])->create();
        }

        $expectedMember = [];
        for ($i = 1; $i <= 10; $i++) {
            $expectedMember[] = [
                '@id' => '/api/topic_posts/' . $posts[$i]->id,
                '@type' => 'TopicPost',
                'id' => $posts[$i]->id,
                'creation_datetime' => $posts[$i]->creationDatetime->format(\DateTimeInterface::ATOM),
                'update_datetime' => null,
                'content' => 'Post content ' . $i,
                'creator' => [
                    '@type' => 'User',
                    'id' => $poster->id,
                    'username' => 'poster',
                    'display_name' => 'poster',
                    'deletion_datetime' => null,
                    'profile_picture' => null,
                ],
                'upvotes' => 0,
                'downvotes' => 0,
                'user_vote' => null,
            ];
        }

        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => $expectedMember,
            'totalItems' => 15,
            'view' => [
                '@id' => '/api/forums/topics/test-topic/posts?page=1',
                '@type' => 'PartialCollectionView',
                'first' => '/api/forums/topics/test-topic/posts?page=1',
                'last' => '/api/forums/topics/test-topic/posts?page=2',
                'next' => '/api/forums/topics/test-topic/posts?page=2',
            ],
        ]);
    }

    public function test_get_collection_user_vote_null_when_authenticated_user_has_not_voted(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory, 'slug' => 'test-forum'])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster = UserFactory::new(['username' => 'poster'])->create();
        $viewer = UserFactory::new(['username' => 'viewer'])->asBaseUser()->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        $voteCache = VoteCacheFactory::new(['upvoteCount' => 0, 'downvoteCount' => 0])->create();
        $post = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster,
            'content' => 'Post with no vote from viewer',
            'creationDatetime' => new \DateTime('2024-01-05 10:00:00'),
            'updateDatetime' => null,
            'voteCache' => $voteCache,
        ])->create();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/topic_posts/' . $post->id,
                    '@type' => 'TopicPost',
                    'id' => $post->id,
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Post with no vote from viewer',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster->id,
                        'username' => 'poster',
                        'display_name' => 'poster',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_collection_user_vote_reflects_authenticated_user_upvote(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory, 'slug' => 'test-forum'])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster = UserFactory::new(['username' => 'poster'])->create();
        $viewer = UserFactory::new(['username' => 'viewer'])->asBaseUser()->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $post = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster,
            'content' => 'Post upvoted by viewer',
            'creationDatetime' => new \DateTime('2024-01-05 10:00:00'),
            'updateDatetime' => null,
            'voteCache' => $voteCache,
        ])->create();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $viewer,
            'value' => 1,
            'identifier' => 'viewer-identifier',
            'entityType' => 'app_forum_post',
            'entityId' => $post->id,
        ])->create();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/topic_posts/' . $post->id,
                    '@type' => 'TopicPost',
                    'id' => $post->id,
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Post upvoted by viewer',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster->id,
                        'username' => 'poster',
                        'display_name' => 'poster',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 1,
                    'downvotes' => 0,
                    'user_vote' => 1,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_collection_user_vote_null_for_anonymous_request(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory, 'slug' => 'test-forum'])->create();

        $author = UserFactory::new(['username' => 'topic_author'])->create();
        $poster = UserFactory::new(['username' => 'poster'])->create();

        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $post = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $poster,
            'content' => 'Post seen by anonymous',
            'creationDatetime' => new \DateTime('2024-01-05 10:00:00'),
            'updateDatetime' => null,
            'voteCache' => $voteCache,
        ])->create();

        // No login — anonymous request with no matching identifier vote in DB.
        $this->client->request('GET', '/api/forums/topics/test-topic/posts');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/topic_posts/' . $post->id,
                    '@type' => 'TopicPost',
                    'id' => $post->id,
                    'creation_datetime' => '2024-01-05T10:00:00+00:00',
                    'update_datetime' => null,
                    'content' => 'Post seen by anonymous',
                    'creator' => [
                        '@type' => 'User',
                        'id' => $poster->id,
                        'username' => 'poster',
                        'display_name' => 'poster',
                        'deletion_datetime' => null,
                        'profile_picture' => null,
                    ],
                    'upvotes' => 1,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
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
        $posts = [];
        for ($i = 1; $i <= 15; $i++) {
            $posts[$i] = ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $poster,
                'content' => 'Post content ' . $i,
                'creationDatetime' => new \DateTime('2024-01-01 10:00:00 +' . $i . ' hours'),
                'updateDatetime' => null,
            ])->create();
        }

        $expectedMember = [];
        for ($i = 11; $i <= 15; $i++) {
            $expectedMember[] = [
                '@id' => '/api/topic_posts/' . $posts[$i]->id,
                '@type' => 'TopicPost',
                'id' => $posts[$i]->id,
                'creation_datetime' => $posts[$i]->creationDatetime->format(\DateTimeInterface::ATOM),
                'update_datetime' => null,
                'content' => 'Post content ' . $i,
                'creator' => [
                    '@type' => 'User',
                    'id' => $poster->id,
                    'username' => 'poster',
                    'display_name' => 'poster',
                    'deletion_datetime' => null,
                    'profile_picture' => null,
                ],
                'upvotes' => 0,
                'downvotes' => 0,
                'user_vote' => null,
            ];
        }

        $this->client->request('GET', '/api/forums/topics/test-topic/posts?page=2');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => $expectedMember,
            'totalItems' => 15,
            'view' => [
                '@id' => '/api/forums/topics/test-topic/posts?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/forums/topics/test-topic/posts?page=1',
                'last' => '/api/forums/topics/test-topic/posts?page=2',
                'previous' => '/api/forums/topics/test-topic/posts?page=1',
            ],
        ]);
    }

    /** One poster per naming case, each named without a query of their own (#1118). */
    public function test_posters_are_named_by_their_public_profile_name_next_to_their_username(): void
    {
        $forum = ForumFactory::new([
            'forumCategory' => ForumCategoryFactory::new(['position' => 1]),
            'slug' => 'test-forum',
        ])->create();
        $named = UserFactory::new()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $named->profile->displayName = 'Alexandre Martin';
        $private = UserFactory::new()->create(['username' => 'crydetest', 'email' => 'crydetest@test.com']);
        $private->profile->displayName = 'Samuel Dupont';
        $private->profile->isPublic = false;
        $departed = UserFactory::new()->create([
            'username' => 'deleted_c7c9f2e1',
            'email' => 'deleted_c7c9f2e1@email.com',
            'deletionDatetime' => new \DateTimeImmutable('2024-06-01 09:00:00'),
        ]);
        self::getContainer()->get('doctrine')->getManager()->flush();
        $topic = ForumTopicFactory::new(['forum' => $forum, 'title' => 'Test Topic', 'slug' => 'test-topic', 'author' => $named])->create();

        $posts = [];
        foreach ([$named, $private, $departed] as $index => $poster) {
            $posts[] = ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $poster,
                'content' => 'Post ' . $index,
                'creationDatetime' => new \DateTime(sprintf('2024-01-0%d 10:00:00', $index + 1)),
                'updateDatetime' => null,
            ])->create();
        }

        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/forums/topics/test-topic/posts');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/forums/topics/test-topic/posts',
            '@type' => 'Collection',
            'member' => [
                $this->expectedPost($posts[0], $named, 'Alexandre Martin', null),
                // A private profile keeps its name to itself.
                $this->expectedPost($posts[1], $private, 'crydetest', null),
                $this->expectedPost($posts[2], $departed, 'Utilisateur supprimé', '2024-06-01T09:00:00+00:00'),
            ],
            'totalItems' => 3,
        ]);
        $this->assertNoQueryReadsTable('user_profile', 'A poster profile must come with the page, never in a query of its own');
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedPost(object $post, object $poster, string $displayName, ?string $deletionDatetime): array
    {
        return [
            '@id' => '/api/topic_posts/' . $post->id,
            '@type' => 'TopicPost',
            'id' => $post->id,
            'creation_datetime' => \DateTimeImmutable::createFromInterface($post->creationDatetime)->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'content' => $post->content,
            'creator' => [
                '@type' => 'User',
                'id' => $poster->id,
                'username' => $poster->username,
                'display_name' => $displayName,
                'deletion_datetime' => $deletionDatetime,
                'profile_picture' => null,
            ],
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ];
    }
}
