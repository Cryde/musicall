<?php

declare(strict_types=1);

namespace App\Tests\Api\Notification;

use App\Enum\Notification\NotificationType;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Notification\NotificationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_feed_returns_only_recipient_notifications_newest_first(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other', 'email' => 'other@test.com']);

        $older = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::ForumTopicReply,
            'payload' => ['topic_slug' => 'my-topic', 'topic_title' => 'My Topic'],
            'creationDatetime' => new \DateTimeImmutable('2026-04-01 10:00:00'),
        ])->create();
        $newer = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::PublicationComment,
            'payload' => ['publication_slug' => 'my-pub'],
            'creationDatetime' => new \DateTimeImmutable('2026-04-02 10:00:00'),
        ])->create();
        NotificationFactory::new(['recipient' => $other])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $newer->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $newer->id,
                    'type' => 'publication_comment',
                    'payload' => ['publication_slug' => 'my-pub'],
                    'read_datetime' => null,
                    'creation_datetime' => $newer->creationDatetime->format(\DATE_ATOM),
                ],
                [
                    '@id' => '/api/user/notifications/' . $older->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $older->id,
                    'type' => 'forum_topic_reply',
                    'payload' => ['topic_slug' => 'my-topic', 'topic_title' => 'My Topic'],
                    'read_datetime' => null,
                    'creation_datetime' => $older->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_count_returns_unread_total(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        NotificationFactory::new(['recipient' => $user])->many(3)->create();
        NotificationFactory::new(['recipient' => $user])->read()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications/count', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationCount',
            '@id' => '/api/user/notifications/count',
            '@type' => 'UserNotificationCount',
            'unread' => 3,
        ]);
    }

    public function test_mark_one_as_read(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $notification = NotificationFactory::new(['recipient' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/notifications/' . $notification->id . '/read', [], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(0, $this->notificationRepository()->countUnread($user));
    }

    public function test_cannot_mark_another_users_notification_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other', 'email' => 'other@test.com']);
        $notification = NotificationFactory::new(['recipient' => $other])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/notifications/' . $notification->id . '/read', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'status' => 404,
            'type' => '/errors/404',
            'title' => 'An error occurred',
            'detail' => 'Notification introuvable',
            'description' => 'Notification introuvable',
        ]);
        $this->assertSame(1, $this->notificationRepository()->countUnread($other));
    }

    public function test_mark_all_as_read(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        NotificationFactory::new(['recipient' => $user])->many(3)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/notifications/mark-all-read', [], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(0, $this->notificationRepository()->countUnread($user));
    }

    public function test_feed_requires_authentication(): void
    {
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_get_item_returns_notification_for_owner(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::ForumTopicReply,
            'payload' => ['topic_slug' => 'my-topic', 'topic_title' => 'My Topic'],
            'creationDatetime' => new \DateTimeImmutable('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications/' . $notification->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications/' . $notification->id,
            '@type' => 'UserNotification',
            'id' => (string) $notification->id,
            'type' => 'forum_topic_reply',
            'payload' => ['topic_slug' => 'my-topic', 'topic_title' => 'My Topic'],
            'read_datetime' => null,
            'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
        ]);
    }

    public function test_cannot_get_another_users_notification_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other', 'email' => 'other@test.com']);
        $notification = NotificationFactory::new(['recipient' => $other])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications/' . $notification->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'status' => 404,
            'type' => '/errors/404',
            'title' => 'An error occurred',
            'detail' => 'Notification introuvable',
            'description' => 'Notification introuvable',
        ]);
    }

    public function test_get_item_requires_authentication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $notification = NotificationFactory::new(['recipient' => $user])->create();

        $this->client->jsonRequest('GET', '/api/user/notifications/' . $notification->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_feed_second_page_returns_the_remaining_notifications(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        // 20 newer notifications fill page 1 ...
        NotificationFactory::new([
            'recipient' => $user,
            'creationDatetime' => new \DateTimeImmutable('2026-04-10 10:00:00'),
        ])->many(20)->create();
        // ... and one strictly-older notification lands alone on page 2.
        $oldest = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::PublicationComment,
            'payload' => ['publication_slug' => 'oldest'],
            'creationDatetime' => new \DateTimeImmutable('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications?page=2', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 21,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $oldest->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $oldest->id,
                    'type' => 'publication_comment',
                    'payload' => ['publication_slug' => 'oldest'],
                    'read_datetime' => null,
                    'creation_datetime' => $oldest->creationDatetime->format(\DATE_ATOM),
                ],
            ],
            'view' => [
                '@id' => '/api/user/notifications?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/user/notifications?page=1',
                'last' => '/api/user/notifications?page=2',
                'previous' => '/api/user/notifications?page=1',
            ],
        ]);
    }

    public function test_marking_an_already_read_notification_keeps_the_original_read_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $readAt = new \DateTimeImmutable('2026-01-01 12:00:00');
        $notification = NotificationFactory::new(['recipient' => $user])->read($readAt)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/notifications/' . $notification->id . '/read', [], ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $refreshed = $this->notificationRepository()->find($notification->id);
        $this->assertNotNull($refreshed);
        $this->assertSame($readAt->format(\DATE_ATOM), $refreshed->readDatetime?->format(\DATE_ATOM));
    }

    public function test_mark_all_read_when_nothing_is_unread_returns_204(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        NotificationFactory::new(['recipient' => $user])->read()->many(2)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/notifications/mark-all-read', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(0, $this->notificationRepository()->countUnread($user));
    }

    public function test_count_requires_authentication(): void
    {
        $this->client->jsonRequest('GET', '/api/user/notifications/count', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_mark_one_read_requires_authentication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $notification = NotificationFactory::new(['recipient' => $user])->create();

        $this->client->jsonRequest('POST', '/api/user/notifications/' . $notification->id . '/read', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_mark_all_read_requires_authentication(): void
    {
        $this->client->jsonRequest('POST', '/api/user/notifications/mark-all-read', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    private function notificationRepository(): NotificationRepository
    {
        return self::getContainer()->get(NotificationRepository::class);
    }
}
