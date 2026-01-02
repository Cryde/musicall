<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Repository\Forum\ForumPostRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumPostPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_post_not_logged(): void
    {
        $this->client->jsonRequest('POST', '/api/forum/posts', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_post(): void
    {
        $forumPostRepository =  static::getContainer()->get(ForumPostRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();
        $topic = ForumTopicFactory::new([
            'author' => UserFactory::new(),
            'forum' => $forum1,
            'postNumber' => 10,
            'slug' => 'topic-title-slug',
            'title' => 'Topic title',
        ])->create();

        //pretest
        $this->assertCount(0, $forumPostRepository->findBy(['topic' => $topic->_real()]));

        $this->client->loginUser($user1->_real());
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                "content" => "test content for new message",
                "topic"   => '/api/forum/topics/' . $topic->getSlug(),
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();
        $results = $forumPostRepository->findBy(['topic' => $topic->_real()]);
        $this->assertCount(1, $results);
        $userId = $user1->getId();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/topic_posts/' . $results[0]->getId(),
            '@type' => 'TopicPost',
            'id'                => $results[0]->getId(),
            'creation_datetime' => $results[0]->getCreationDatetime()->format('c'),
            'content'           => 'test content for new message',
            'creator'           => [
                '@type' => 'User',
                'id'              => $userId,
                'username'        => 'base_admin',
            ],
        ]);
    }

    public function test_post_with_empty_content(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();
        $topic = ForumTopicFactory::new([
            'author' => UserFactory::new(),
            'forum' => $forum1,
            'postNumber' => 10,
            'slug' => 'topic-title-slug',
            'title' => 'Topic title',
        ])->create();

        $this->client->loginUser($user1->_real());
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => '',
                'topic' => '/api/forum/topics/' . $topic->getSlug(),
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'content',
                    'message' => 'Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => "content: Cette valeur ne doit pas être vide.\ncontent: Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.",
            'description' => "content: Cette valeur ne doit pas être vide.\ncontent: Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.",
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
        ]);
    }

    public function test_post_with_content_too_short(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();
        $topic = ForumTopicFactory::new([
            'author' => UserFactory::new(),
            'forum' => $forum1,
            'postNumber' => 10,
            'slug' => 'topic-title-slug',
            'title' => 'Topic title',
        ])->create();

        $this->client->loginUser($user1->_real());
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => 'short',
                'topic' => '/api/forum/topics/' . $topic->getSlug(),
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => 'content: Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.',
            'description' => 'content: Cette chaîne est trop courte. Elle doit avoir au minimum 10 caractères.',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
        ]);
    }

    public function test_post_with_missing_topic(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user1->_real());
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => 'This is a valid content message',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'topic',
                    'message' => 'Cette valeur ne doit pas être nulle.',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
            'detail' => 'topic: Cette valeur ne doit pas être nulle.',
            'description' => 'topic: Cette valeur ne doit pas être nulle.',
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
        ]);
    }
}
