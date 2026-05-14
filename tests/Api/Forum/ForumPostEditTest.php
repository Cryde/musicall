<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Repository\Forum\ForumPostRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class ForumPostEditTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array UNAUTH_BODY = [
        'code' => 401,
        'message' => 'JWT Token not found',
    ];

    private const array NOT_FOUND_BODY = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/404',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Message de forum inexistant',
        'description' => 'Message de forum inexistant',
        'status' => 404,
        'type' => '/errors/404',
    ];

    private const array FORBIDDEN_BODY = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/403',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Vous ne pouvez pas modifier ce message.',
        'description' => 'Vous ne pouvez pas modifier ce message.',
        'status' => 403,
        'type' => '/errors/403',
    ];

    public function test_edit_unauthenticated_returns_401(): void
    {
        $post = $this->createPost();

        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => 'updated content here'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(self::UNAUTH_BODY);
    }

    public function test_edit_unknown_id_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/forum/posts/00000000-0000-0000-0000-000000000000/edit',
            ['content' => 'updated content here'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND_BODY);
    }

    public function test_edit_by_random_user_returns_403(): void
    {
        $post = $this->createPost();
        $other = UserFactory::new(['username' => 'other_user', 'email' => 'other@email.com'])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => 'updated content here'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals(self::FORBIDDEN_BODY);
        $repository = static::getContainer()->get(ForumPostRepository::class);
        $this->assertSame('original content here', $repository->find($post->id)->content);
        $this->assertNull($repository->find($post->id)->updateDatetime);
    }

    public function test_edit_by_author_succeeds(): void
    {
        $post = $this->createPost();

        $this->client->loginUser($post->creator);
        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => 'updated content here'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $repository = static::getContainer()->get(ForumPostRepository::class);
        $fresh = $repository->find($post->id);
        $this->assertSame('updated content here', $fresh->content);
        $this->assertNotNull($fresh->updateDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/topic_posts/' . $fresh->id,
            '@type' => 'TopicPost',
            'id' => $fresh->id,
            'creation_datetime' => $fresh->creationDatetime->format('c'),
            'update_datetime' => $fresh->updateDatetime->format('c'),
            'content' => 'updated content here',
            'creator' => [
                '@type' => 'User',
                'id' => $post->creator->id,
                'username' => 'post_author',
            ],
            'upvotes' => 0,
            'downvotes' => 0,
        ]);
    }

    public function test_edit_by_admin_succeeds(): void
    {
        $post = $this->createPost();
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => 'admin edited content'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $repository = static::getContainer()->get(ForumPostRepository::class);
        $fresh = $repository->find($post->id);
        $this->assertSame('admin edited content', $fresh->content);
        $this->assertNotNull($fresh->updateDatetime);
    }

    public function test_edit_with_blank_content_returns_422(): void
    {
        $post = $this->createPost();

        $this->client->loginUser($post->creator);
        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => ''],
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

    public function test_edit_with_content_too_short_returns_422(): void
    {
        $post = $this->createPost();

        $this->client->loginUser($post->creator);
        $this->client->jsonRequest('POST', '/api/forum/posts/' . $post->id . '/edit',
            ['content' => 'short'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function createPost(): \App\Entity\Forum\ForumPost
    {
        $author = UserFactory::new(['username' => 'post_author', 'email' => 'post_author@email.com'])->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory = ForumCategoryFactory::new(['position' => 1, 'forumSource' => $forumSource])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $topic = ForumTopicFactory::new([
            'author' => $author,
            'forum' => $forum,
            'slug' => 'topic-slug',
            'title' => 'Topic',
        ])->create();

        return ForumPostFactory::new([
            'creator' => $author,
            'topic' => $topic,
            'content' => 'original content here',
            'updateDatetime' => null,
        ])->create();
    }
}
