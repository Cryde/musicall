<?php declare(strict_types=1);

namespace App\Tests\Api\Comment;

use App\Repository\Comment\CommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CommentGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_comments_user_vote_null_when_not_voted(): void
    {
        $thread = CommentThreadFactory::new()->create();
        $user = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'author@test.com']);
        $comment = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Salut'])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/comments?thread=' . $thread->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $refreshed = self::getContainer()->get(CommentRepository::class)->find($comment->_real()->id);
        $this->assertJsonEquals($this->expectedCollection($thread, $comment, $author, $refreshed, 0, 0, null));
    }

    public function test_get_comments_user_vote_populated_when_user_has_voted(): void
    {
        $thread = CommentThreadFactory::new()->create();
        $user = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'author@test.com']);
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $comment = CommentFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => 'Salut',
        ])->create();
        $comment->_real()->voteCache = $voteCache->_real();
        $comment->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_comment',
            'entityId' => (string) $comment->_real()->id,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/comments?thread=' . $thread->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $refreshed = self::getContainer()->get(CommentRepository::class)->find($comment->_real()->id);
        $this->assertJsonEquals($this->expectedCollection($thread, $comment, $author, $refreshed, 1, 0, 1));
    }

    public function test_get_comments_user_vote_null_for_anonymous(): void
    {
        $thread = CommentThreadFactory::new()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'author@test.com']);
        $comment = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Salut'])->create();

        $this->client->jsonRequest(
            'GET',
            '/api/comments?thread=' . $thread->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $refreshed = self::getContainer()->get(CommentRepository::class)->find($comment->_real()->id);
        $this->assertJsonEquals($this->expectedCollection($thread, $comment, $author, $refreshed, 0, 0, null));
    }

    private function expectedCollection(
        object $thread,
        object $comment,
        object $author,
        object $refreshed,
        int $upvotes,
        int $downvotes,
        ?int $userVote,
    ): array {
        return [
            '@context' => '/api/contexts/Comment',
            '@id' => '/api/comments',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/comments/' . $comment->_real()->id,
                    '@type' => 'Comment',
                    'id' => $comment->_real()->id,
                    'thread_id' => $thread->_real()->id,
                    'author' => [
                        'id' => $author->_real()->id,
                        'username' => 'author',
                        'profile_picture_url' => null,
                        'deletion_datetime' => null,
                    ],
                    'content' => 'Salut',
                    'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
                    'upvotes' => $upvotes,
                    'downvotes' => $downvotes,
                    'user_vote' => $userVote,
                ],
            ],
            'view' => [
                '@id' => '/api/comments?thread=' . $thread->_real()->id,
                '@type' => 'PartialCollectionView',
            ],
        ];
    }
}
