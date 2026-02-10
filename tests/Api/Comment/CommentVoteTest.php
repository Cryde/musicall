<?php

namespace App\Tests\Api\Comment;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CommentVoteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_post_vote_upvote(): void
    {
        $comment = $this->createComment();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/comments/' . $comment->getId() . '/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CommentVoteSummary',
            '@id' => '/api/comment_vote_summaries/' . $comment->getId(),
            '@type' => 'CommentVoteSummary',
            'upvotes' => 1,
            'downvotes' => 0,
            'user_vote' => 1,
        ]);
    }

    public function test_post_vote_toggle_off(): void
    {
        $comment = $this->createComment();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $comment->_real()->setVoteCache($voteCache->_real());
        $comment->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_comment',
            'entityId' => (string) $comment->_real()->getId(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/comments/' . $comment->getId() . '/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CommentVoteSummary',
            '@id' => '/api/comment_vote_summaries/' . $comment->getId(),
            '@type' => 'CommentVoteSummary',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ]);
    }

    public function test_post_vote_change_direction(): void
    {
        $comment = $this->createComment();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $comment->_real()->setVoteCache($voteCache->_real());
        $comment->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_comment',
            'entityId' => (string) $comment->_real()->getId(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/comments/' . $comment->getId() . '/vote',
            ['user_vote' => -1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/CommentVoteSummary',
            '@id' => '/api/comment_vote_summaries/' . $comment->getId(),
            '@type' => 'CommentVoteSummary',
            'upvotes' => 0,
            'downvotes' => 1,
            'user_vote' => -1,
        ]);
    }

    public function test_vote_on_nonexistent_comment(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/comments/999999/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Commentaire inexistant',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Commentaire inexistant',
        ]);
    }

    public function test_post_vote_invalid_value(): void
    {
        $comment = $this->createComment();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/comments/' . $comment->getId() . '/vote',
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

    private function createComment(): object
    {
        $thread = CommentThreadFactory::new()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        return CommentFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => 'Test comment content',
        ])->create();
    }
}
