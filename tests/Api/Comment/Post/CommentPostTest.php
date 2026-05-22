<?php

namespace App\Tests\Api\Comment\Post;

use App\Repository\Comment\CommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\Comment\ValidReplyParent;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class CommentPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_post(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $commentThread = CommentThreadFactory::new()->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->id,
            'content' => "This is a comment
with multiline",
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $commentRepo = static::getContainer()->get(CommentRepository::class);
        $comments = $commentRepo->findAll();
        $this->assertCount(1, $comments);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Comment',
            '@id' => '/api/comments/' . $comments[0]->id,
            '@type' => 'Comment',
            'id' => $comments[0]->id,
            'thread_id' => $commentThread->id,
            'author' => [
                'id' => $user1->id,
                'username' => 'base_user_1',
                'profile_picture_url' => null,
                'deletion_datetime' => null,
            ],
            'creation_datetime' => $comments[0]->creationDatetime->format('c'),
            'content' => "This is a comment<br />\nwith multiline",
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
            'parent_id' => null,
        ]);
    }

    public function test_post_reply_to_root_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'author', 'email' => 'author@test.com']);
        $replier = UserFactory::new()->asBaseUser()->create(['username' => 'replier', 'email' => 'replier@test.com']);
        $thread = CommentThreadFactory::new()->create();
        $parent = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Question'])->create();

        $this->client->loginUser($replier);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'   => '/api/comment_threads/' . $thread->id,
            'content'  => 'Voici la réponse',
            'parentId' => $parent->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $commentRepo = static::getContainer()->get(CommentRepository::class);
        $reply = $commentRepo->findOneBy(['parent' => $parent->id]);
        self::assertNotNull($reply);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Comment',
            '@id' => '/api/comments/' . $reply->id,
            '@type' => 'Comment',
            'id' => $reply->id,
            'thread_id' => $thread->id,
            'parent_id' => $parent->id,
            'author' => [
                'id' => $replier->id,
                'username' => 'replier',
                'profile_picture_url' => null,
                'deletion_datetime' => null,
            ],
            'creation_datetime' => $reply->creationDatetime->format('c'),
            'content' => 'Voici la réponse',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ]);
    }

    public function test_post_reply_to_already_nested_comment_is_rejected(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'author', 'email' => 'author@test.com']);
        $replier = UserFactory::new()->asBaseUser()->create(['username' => 'replier', 'email' => 'replier@test.com']);
        $thread = CommentThreadFactory::new()->create();
        $root = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'Root'])->create();
        $firstReply = CommentFactory::new(['thread' => $thread, 'author' => $replier, 'content' => 'First reply', 'parent' => $root])->create();

        $this->client->loginUser($replier);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'   => '/api/comment_threads/' . $thread->id,
            'content'  => 'Reply to a reply',
            'parentId' => $firstReply->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context'  => '/api/contexts/ConstraintViolation',
            '@id'       => '/api/validation_errors/' . ValidReplyParent::ALREADY_NESTED_CODE,
            '@type'     => 'ConstraintViolation',
            'status'    => 422,
            'violations' => [
                [
                    'propertyPath' => 'parent_id',
                    'message'      => 'Vous ne pouvez pas répondre à une réponse.',
                    'code'         => ValidReplyParent::ALREADY_NESTED_CODE,
                ],
            ],
            'detail'      => 'parent_id: Vous ne pouvez pas répondre à une réponse.',
            'description' => 'parent_id: Vous ne pouvez pas répondre à une réponse.',
            'type'        => '/validation_errors/' . ValidReplyParent::ALREADY_NESTED_CODE,
            'title'       => 'An error occurred',
        ]);
    }

    public function test_post_reply_to_parent_in_different_thread_is_rejected(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'author', 'email' => 'author@test.com']);
        $replier = UserFactory::new()->asBaseUser()->create(['username' => 'replier', 'email' => 'replier@test.com']);
        $threadA = CommentThreadFactory::new()->create();
        $threadB = CommentThreadFactory::new()->create();
        $parent = CommentFactory::new(['thread' => $threadA, 'author' => $author, 'content' => 'Parent in A'])->create();

        $this->client->loginUser($replier);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'   => '/api/comment_threads/' . $threadB->id,
            'content'  => 'Reply targeting wrong thread',
            'parentId' => $parent->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context'  => '/api/contexts/ConstraintViolation',
            '@id'       => '/api/validation_errors/' . ValidReplyParent::WRONG_THREAD_CODE,
            '@type'     => 'ConstraintViolation',
            'status'    => 422,
            'violations' => [
                [
                    'propertyPath' => 'parent_id',
                    'message'      => "Le commentaire parent n'appartient pas à ce fil de discussion.",
                    'code'         => ValidReplyParent::WRONG_THREAD_CODE,
                ],
            ],
            'detail'      => "parent_id: Le commentaire parent n'appartient pas à ce fil de discussion.",
            'description' => "parent_id: Le commentaire parent n'appartient pas à ce fil de discussion.",
            'type'        => '/validation_errors/' . ValidReplyParent::WRONG_THREAD_CODE,
            'title'       => 'An error occurred',
        ]);
    }

    public function test_post_reply_to_unknown_parent_is_rejected(): void
    {
        $replier = UserFactory::new()->asBaseUser()->create(['username' => 'replier', 'email' => 'replier@test.com']);
        $thread = CommentThreadFactory::new()->create();

        $this->client->loginUser($replier);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'   => '/api/comment_threads/' . $thread->id,
            'content'  => 'Reply to nothing',
            'parentId' => 999999,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context'  => '/api/contexts/ConstraintViolation',
            '@id'       => '/api/validation_errors/' . ValidReplyParent::NOT_FOUND_CODE,
            '@type'     => 'ConstraintViolation',
            'status'    => 422,
            'violations' => [
                [
                    'propertyPath' => 'parent_id',
                    'message'      => 'Commentaire parent introuvable.',
                    'code'         => ValidReplyParent::NOT_FOUND_CODE,
                ],
            ],
            'detail'      => 'parent_id: Commentaire parent introuvable.',
            'description' => 'parent_id: Commentaire parent introuvable.',
            'type'        => '/validation_errors/' . ValidReplyParent::NOT_FOUND_CODE,
            'title'       => 'An error occurred',
        ]);
    }

    public function test_not_logged(): void
    {
        $commentThread = CommentThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->id,
            'content' => 'content',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_with_invalid_value(): void
    {
        $commentThread = CommentThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->id,
            'content' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le commentaire est vide',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ]
            ],
            'detail' => 'content: Le commentaire est vide',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            '@context' => '/api/contexts/ConstraintViolation',
            'description' => 'content: Le commentaire est vide',
        ]);
    }
}
