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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class ForumTopicPostPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_post_topic_not_logged(): void
    {
        $this->client->jsonRequest('POST', '/api/forum/topic/post', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_post_topic(): void
    {
        $forumTopicRepository =  static::getContainer()->get(ForumTopicRepository::class);
        $forumPostRepository =  static::getContainer()->get(ForumPostRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();

        //pretest
        $this->assertCount(0, $forumTopicRepository->findBy(['forum' => $forum1]));
        $this->assertCount(0, $forumPostRepository->findAll());

        $userId = $user1->id;
        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/topic/post',
            [
                "title" => "Title for this new topic",
                "message" => "test content for new topic",
                "forum"   => '/api/forum/' . $forum1->slug,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();
        $results = $forumTopicRepository->findBy(['forum' => $forum1]);
        $this->assertCount(1, $results);
        $this->assertCount(1, $forumPostRepository->findBy(['topic' => $results[0]]));
        $posts = $forumPostRepository->findBy(['topic' => $results[0]]);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumTopic',
            '@id' => '/api/forum_topics/' . $results[0]->slug,
            '@type' => 'ForumTopic',
            'id' => $results[0]->id,
            'title' => 'Title for this new topic',
            'slug' => 'title-for-this-new-topic',
            'type' => 0,
            'is_locked' => false,
            'is_resolved' => false,
            'is_pinned' => false,
            'last_post' => [
                '@type' => 'ForumPost',
                'id' => $posts[0]->id,
                'creation_datetime' => $posts[0]->creationDatetime->format('c'),
                'creator' => [
                    '@type' => 'User',
                    'id' => $userId,
                    'username' => 'base_admin',
                    'display_name' => 'base_admin',
                ],
            ],
            'creation_datetime' => $results[0]->creationDatetime->format('c'),
            'author' => [
                '@type' => 'User',
                'id' => $userId,
                'username' => 'base_admin',
                'display_name' => 'base_admin',
            ],
            'post_number' => 1,
        ]);
    }

    public function test_a_new_topic_takes_its_forum_locale_not_the_page_locale(): void
    {
        $forumTopicRepository = static::getContainer()->get(ForumTopicRepository::class);
        $forumPostRepository = static::getContainer()->get(ForumPostRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $englishCategory = ForumCategoryFactory::new(['position' => 1, 'title' => 'General', 'locale' => 'en'])->create();
        $englishForum = ForumFactory::new(['forumCategory' => $englishCategory, 'slug' => 'introductions'])->create();

        $userId = $user->id;
        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forum/topic/post',
            [
                'title' => 'Hello from a French page',
                'message' => 'test content for new topic',
                'forum' => '/api/forum/introductions',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_X_LOCALE' => 'fr']
        );

        $this->assertResponseIsSuccessful();
        $topics = $forumTopicRepository->findBy(['forum' => $englishForum]);
        $this->assertCount(1, $topics);
        $posts = $forumPostRepository->findBy(['topic' => $topics[0]]);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumTopic',
            '@id' => '/api/forum_topics/hello-from-a-french-page',
            '@type' => 'ForumTopic',
            'id' => $topics[0]->id,
            'title' => 'Hello from a French page',
            'slug' => 'hello-from-a-french-page',
            'type' => 0,
            'is_locked' => false,
            'is_resolved' => false,
            'is_pinned' => false,
            'last_post' => [
                '@type' => 'ForumPost',
                'id' => $posts[0]->id,
                'creation_datetime' => $posts[0]->creationDatetime->format('c'),
                'creator' => [
                    '@type' => 'User',
                    'id' => $userId,
                    'username' => 'base_admin',
                    'display_name' => 'base_admin',
                ],
            ],
            'creation_datetime' => $topics[0]->creationDatetime->format('c'),
            'author' => [
                '@type' => 'User',
                'id' => $userId,
                'username' => 'base_admin',
                'display_name' => 'base_admin',
            ],
            'post_number' => 1,
        ]);
        // Listed with the English forums, where it was posted, and not with the French ones.
        $this->assertSame(
            ['hello-from-a-french-page'],
            array_map(static fn ($topic): string => $topic->slug, $forumTopicRepository->findRecentlyActive('en', 3)),
        );
        $this->assertSame([], $forumTopicRepository->findRecentlyActive('fr', 3));
    }

    public function test_post_topic_validation(): void
    {
        $forumTopicRepository =  static::getContainer()->get(ForumTopicRepository::class);
        $forumPostRepository =  static::getContainer()->get(ForumPostRepository::class);
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();

        //pretest
        $this->assertCount(0, $forumTopicRepository->findBy(['forum' => $forum1]));
        $this->assertCount(0, $forumPostRepository->findAll());

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/topic/post',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'title'       => 'An error occurred',
            'description' => 'forum: Cette valeur ne doit pas être vide.
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
            '@context' => '/api/contexts/ConstraintViolation',
        ]);

        $results = $forumTopicRepository->findBy(['forum' => $forum1]);
        $this->assertCount(0, $results);
        $this->assertCount(0, $forumPostRepository->findAll());
    }
}
