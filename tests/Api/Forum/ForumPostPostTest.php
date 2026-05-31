<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Enum\Notification\NotificationType;
use App\Repository\Forum\ForumPostRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Forum\ForumTopicParticipationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class ForumPostPostTest extends ApiTestCase
{
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
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20, 'postNumber' => 5])->create();
        $topic = ForumTopicFactory::new([
            'author' => UserFactory::new(),
            'forum' => $forum1,
            'postNumber' => 10,
            'slug' => 'topic-title-slug',
            'title' => 'Topic title',
        ])->create();

        //pretest
        $this->assertCount(0, $forumPostRepository->findBy(['topic' => $topic]));
        $this->assertSame(10, $topic->postNumber);
        $this->assertSame(5, $forum1->postNumber);

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                "content" => "test content for new message",
                "topic"   => '/api/forums/topics/' . $topic->slug,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();
        $results = $forumPostRepository->findBy(['topic' => $topic]);
        $this->assertCount(1, $results);
        $userId = $user1->id;
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TopicPost',
            '@id' => '/api/topic_posts/' . $results[0]->id,
            '@type' => 'TopicPost',
            'id'                => $results[0]->id,
            'creation_datetime' => $results[0]->creationDatetime->format('c'),
            'content'           => 'test content for new message',
            'creator'           => [
                '@type' => 'User',
                'id'              => $userId,
                'username'        => 'base_admin',
            ],
            'upvotes' => 0,
            'downvotes' => 0,
        ]);

        // Verify counters are incremented
        \Zenstruck\Foundry\Persistence\refresh($topic);
        \Zenstruck\Foundry\Persistence\refresh($forum1);
        $this->assertSame(11, $topic->postNumber);
        $this->assertSame(6, $forum1->postNumber);
        $this->assertSame($results[0]->id, $topic->lastPost->id);
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

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => '',
                'topic' => '/api/forums/topics/' . $topic->slug,
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

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => 'short',
                'topic' => '/api/forums/topics/' . $topic->slug,
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

        $this->client->loginUser($user1);
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

    public function test_post_on_locked_topic(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();
        $topic = ForumTopicFactory::new([
            'author' => UserFactory::new(),
            'forum' => $forum1,
            'postNumber' => 10,
            'slug' => 'locked-topic-slug',
            'title' => 'Locked Topic',
            'isLocked' => true,
        ])->create();

        $this->client->loginUser($user1);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            [
                'content' => 'This is a valid content message',
                'topic' => '/api/forums/topics/' . $topic->slug,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'status' => 400,
            'detail' => 'Ce sujet est verrouillé. Vous ne pouvez plus y répondre.',
            'description' => 'Ce sujet est verrouillé. Vous ne pouvez plus y répondre.',
            'title' => 'An error occurred',
            'type' => '/errors/400',
        ]);
    }

    public function test_reply_notifies_author_and_active_participants_not_the_poster(): void
    {
        $author = UserFactory::new()->create(['username' => 'topic_author', 'email' => 'author@test.com']);
        $participant1 = UserFactory::new()->create(['username' => 'participant_one', 'email' => 'p1@test.com']);
        $participant2 = UserFactory::new()->create(['username' => 'participant_two', 'email' => 'p2@test.com']);
        $removedParticipant = UserFactory::new()->create(['username' => 'removed_one', 'email' => 'removed@test.com']);
        $poster = UserFactory::new()->asBaseUser()->create();

        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory = ForumCategoryFactory::new(['title' => 'Forum category', 'forumSource' => $forumSource])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $topic = ForumTopicFactory::new([
            'author' => $author,
            'forum' => $forum,
            'slug' => 'topic-title-slug',
            'title' => 'Topic title',
        ])->create();
        ForumTopicParticipationFactory::new(['user' => $participant1, 'topic' => $topic])->create();
        ForumTopicParticipationFactory::new(['user' => $participant2, 'topic' => $topic])->create();
        ForumTopicParticipationFactory::new(['user' => $removedParticipant, 'topic' => $topic, 'removedDatetime' => new \DateTime()])->create();

        $this->client->loginUser($poster);
        $this->client->jsonRequest('POST', '/api/forum/posts', [
            'content' => 'Voici ma réponse au sujet',
            'topic' => '/api/forums/topics/' . $topic->slug,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $post = self::getContainer()->get(ForumPostRepository::class)->findBy(['topic' => $topic])[0];
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);

        $expectedPayload = [
            'topic_id' => (string) $topic->id,
            'topic_slug' => 'topic-title-slug',
            'topic_title' => 'Topic title',
            'post_id' => (string) $post->id,
            'actor_id' => (string) $poster->id,
            'actor_username' => $poster->username,
        ];

        foreach ([$author, $participant1, $participant2] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::ForumTopicReply, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        $this->assertCount(0, $notificationRepository->findForRecipient($poster, 10, 0));
        $this->assertCount(0, $notificationRepository->findForRecipient($removedParticipant, 10, 0));
    }

    public function test_reply_by_the_topic_author_does_not_notify_them(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory = ForumCategoryFactory::new(['title' => 'Forum category', 'forumSource' => $forumSource])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $topic = ForumTopicFactory::new([
            'author' => $author,
            'forum' => $forum,
            'slug' => 'my-own-topic',
            'title' => 'My own topic',
        ])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/forum/posts', [
            'content' => 'Je réponds à mon propre sujet',
            'topic' => '/api/forums/topics/' . $topic->slug,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0));
    }
}
