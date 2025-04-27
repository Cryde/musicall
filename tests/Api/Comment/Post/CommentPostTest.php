<?php

namespace Api\Comment\Post;

use App\Repository\Comment\CommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CommentPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_post(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $commentThread = CommentThreadFactory::new()->create();

        $user1 = $user1->_real();
        $commentThread = $commentThread->_real();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->getId(),
            'content' => "This is a comment
with multiline",
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $commentRepo = static::getContainer()->get(CommentRepository::class);
        $comments = $commentRepo->findAll();
        $this->assertCount(1, $comments);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Comment',
            '@id' => '/api/comments/' . $comments[0]->getId(),
            '@type' => 'Comment',
            'id' => $comments[0]->getId(),
            'author' => [
                '@id' => '/api/users/self',
                '@type' => 'User',
                'username' => 'base_user_1',
            ],
            'creation_datetime' => $comments[0]->getCreationDatetime()->format('c'),
            'content' => "This is a comment<br />\nwith multiline",
        ]);
    }

    public function test_not_logged(): void
    {
        $commentThread = CommentThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->_real()->getId(),
            'content' => 'content',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_with_invalid_value(): void
    {
        $commentThread = CommentThreadFactory::new()->create();
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $commentThread->_real()->getId(),
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