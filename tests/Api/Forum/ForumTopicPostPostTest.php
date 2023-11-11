<?php

namespace App\Tests\Api\Forum;

use App\Repository\Forum\ForumPostRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumTopicPostPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_post_topic_not_logged()
    {
        $this->client->jsonRequest('POST', '/api/forum/topic/post', [], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_post_topic()
    {
        $forumTopicRepository =  static::getContainer()->get(ForumTopicRepository::class);
        $forumPostRepository =  static::getContainer()->get(ForumPostRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();

        //pretest
        $this->assertCount(0, $forumTopicRepository->findBy(['forum' => $forum1->object()]));
        $this->assertCount(0, $forumPostRepository->findAll());

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/forum/topic/post',
            [
                "title" => "Title for this new topic",
                "message" => "test content for new topic",
                "forum"   => '/api/forums/' . $forum1->getSlug(),
            ],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();
        $results = $forumTopicRepository->findBy(['forum' => $forum1->object()]);
        $this->assertCount(1, $results);
        $this->assertCount(1, $forumPostRepository->findBy(['topic' => $results[0]]));
        $this->assertJsonEquals([
            'id'                => $results[0]->getId(),
            'forum'             => [
                'id'    => $forum1->object()->getId(),
                'title' => $forum1->object()->getTitle(),
                'slug'  => $forum1->object()->getSlug(),
            ],
            'title'             => 'Title for this new topic',
            'slug'              => 'title-for-this-new-topic',
        ]);
    }

    public function test_post_topic_validation()
    {
        $forumTopicRepository =  static::getContainer()->get(ForumTopicRepository::class);
        $forumPostRepository =  static::getContainer()->get(ForumPostRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();

        //pretest
        $this->assertCount(0, $forumTopicRepository->findBy(['forum' => $forum1->object()]));
        $this->assertCount(0, $forumPostRepository->findAll());

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/forum/topic/post',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'forum: Cette valeur ne doit pas être vide.
title: Cette valeur ne doit pas être vide.
message: Cette valeur ne doit pas être vide.',
            'violations'        => [
                [
                    'propertyPath' => 'forum',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'title',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'message',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'status'            => 422,
            'detail'            => 'forum: Cette valeur ne doit pas être vide.
title: Cette valeur ne doit pas être vide.
message: Cette valeur ne doit pas être vide.',
            'type'              => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title'             => 'An error occurred',
        ]);

        $results = $forumTopicRepository->findBy(['forum' => $forum1->object()]);
        $this->assertCount(0, $results);
        $this->assertCount(0, $forumPostRepository->findAll());
    }
}