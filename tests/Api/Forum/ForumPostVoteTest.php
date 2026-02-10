<?php

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
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumPostVoteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_post_vote_upvote(): void
    {
        $forumPost = $this->createForumPost();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $forumPost->getId() . '/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumPostVoteSummary',
            '@id' => '/api/forum_post_vote_summaries/' . $forumPost->getId(),
            '@type' => 'ForumPostVoteSummary',
            'upvotes' => 1,
            'downvotes' => 0,
            'user_vote' => 1,
        ]);
    }

    public function test_post_vote_toggle_off(): void
    {
        $forumPost = $this->createForumPost();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $forumPost->_real()->setVoteCache($voteCache->_real());
        $forumPost->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_forum_post',
            'entityId' => $forumPost->_real()->getId(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $forumPost->getId() . '/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumPostVoteSummary',
            '@id' => '/api/forum_post_vote_summaries/' . $forumPost->getId(),
            '@type' => 'ForumPostVoteSummary',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ]);
    }

    public function test_post_vote_change_direction(): void
    {
        $forumPost = $this->createForumPost();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $forumPost->_real()->setVoteCache($voteCache->_real());
        $forumPost->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_forum_post',
            'entityId' => $forumPost->_real()->getId(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $forumPost->getId() . '/vote',
            ['user_vote' => -1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumPostVoteSummary',
            '@id' => '/api/forum_post_vote_summaries/' . $forumPost->getId(),
            '@type' => 'ForumPostVoteSummary',
            'upvotes' => 0,
            'downvotes' => 1,
            'user_vote' => -1,
        ]);
    }

    public function test_vote_on_nonexistent_post(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/00000000-0000-0000-0000-000000000000/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message de forum inexistant',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Message de forum inexistant',
        ]);
    }

    public function test_post_vote_invalid_value(): void
    {
        $forumPost = $this->createForumPost();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $forumPost->getId() . '/vote',
            ['user_vote' => 5],
            self::SERVER_PARAMS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'user_vote',
                    'message' => 'La valeur du vote doit être 1 ou -1.',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'user_vote: La valeur du vote doit être 1 ou -1.',
            'description' => 'user_vote: La valeur du vote doit être 1 ou -1.',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
        ]);
    }

    private function createForumPost(): object
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory, 'slug' => 'test-forum'])->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        return ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $author,
            'content' => 'Test forum post content',
            'updateDatetime' => null,
        ])->create();
    }
}
