<?php

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

    public function test_post_not_logged()
    {
        $this->client->jsonRequest('POST', '/api/forum_posts', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_post()
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
        $this->assertCount(0, $forumPostRepository->findBy(['topic' => $topic->object()]));

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/forum_posts',
            [
                "content" => "test content for new message",
                "topic"   => '/api/forum_topics/' . $topic->getSlug(),
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();
        $results = $forumPostRepository->findBy(['topic' => $topic->object()]);
        $this->assertCount(1, $results);
        $this->assertJsonEquals([
            'id'                => $results[0]->getId(),
            'creation_datetime' => $results[0]->getCreationDatetime()->format('c'),
            'content'           => 'test content for new message',
            'creator'           => [
                'username'        => 'base_admin',
                'profile_picture' => null,
            ],
        ]);
    }
}