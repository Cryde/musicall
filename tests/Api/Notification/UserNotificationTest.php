<?php

declare(strict_types=1);

namespace App\Tests\Api\Notification;

use App\Enum\BandSpace\InvitationStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
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

    public function test_invitation_notification_feed_exposes_pending_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        BandSpaceInvitationFactory::new(['token' => 'inv-token-pending', 'status' => InvitationStatus::Pending])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-1',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-pending',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_invitation',
                    'payload' => [
                        'band_space_id' => 'bs-1',
                        'band_space_name' => 'The Rockers',
                        'invitation_token' => 'inv-token-pending',
                        'invited_by_username' => 'admin_user',
                        'invitation_status' => 'pending',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_invitation_notification_feed_reflects_accepted_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        BandSpaceInvitationFactory::new(['token' => 'inv-token-accepted', 'status' => InvitationStatus::Accepted])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-2',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-accepted',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_invitation',
                    'payload' => [
                        'band_space_id' => 'bs-2',
                        'band_space_name' => 'The Rockers',
                        'invitation_token' => 'inv-token-accepted',
                        'invited_by_username' => 'admin_user',
                        'invitation_status' => 'accepted',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_invitation_notification_feed_marks_time_expired_invitation_as_expired(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        // Still status Pending, but past its expiration (the expire command may not have run yet).
        BandSpaceInvitationFactory::new([
            'token' => 'inv-token-expired',
            'status' => InvitationStatus::Pending,
            'expirationDatetime' => new \DateTime('-1 day'),
        ])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-3',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-expired',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_invitation',
                    'payload' => [
                        'band_space_id' => 'bs-3',
                        'band_space_name' => 'The Rockers',
                        'invitation_token' => 'inv-token-expired',
                        'invited_by_username' => 'admin_user',
                        'invitation_status' => 'expired',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_invitation_notification_item_endpoint_exposes_live_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        BandSpaceInvitationFactory::new(['token' => 'inv-token-item', 'status' => InvitationStatus::Pending])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-item',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-item',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications/' . $notification->id, [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications/' . $notification->id,
            '@type' => 'UserNotification',
            'id' => (string) $notification->id,
            'type' => 'band_space_invitation',
            'payload' => [
                'band_space_id' => 'bs-item',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-item',
                'invited_by_username' => 'admin_user',
                'invitation_status' => 'pending',
            ],
            'read_datetime' => null,
            'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
        ]);
    }

    public function test_invitation_notification_feed_reflects_declined_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        BandSpaceInvitationFactory::new(['token' => 'inv-token-declined', 'status' => InvitationStatus::Declined])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-declined',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-declined',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_invitation',
                    'payload' => [
                        'band_space_id' => 'bs-declined',
                        'band_space_name' => 'The Rockers',
                        'invitation_token' => 'inv-token-declined',
                        'invited_by_username' => 'admin_user',
                        'invitation_status' => 'declined',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_invitation_notification_with_unknown_token_is_marked_expired(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        // No matching invitation for this token (e.g. the invite was hard-deleted with its band space).
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceInvitation,
            'payload' => [
                'band_space_id' => 'bs-gone',
                'band_space_name' => 'The Rockers',
                'invitation_token' => 'inv-token-unknown',
                'invited_by_username' => 'admin_user',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_invitation',
                    'payload' => [
                        'band_space_id' => 'bs-gone',
                        'band_space_name' => 'The Rockers',
                        'invitation_token' => 'inv-token-unknown',
                        'invited_by_username' => 'admin_user',
                        'invitation_status' => 'expired',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_task_assignment_notification_refreshes_to_the_live_task_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Titre actuel'])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceTaskAssignment,
            'payload' => [
                'band_space_id' => (string) $bandSpace->id,
                'task_id' => (string) $task->id,
                'task_title' => 'Ancien titre',
                'actor_id' => 'actor-1',
                'actor_username' => 'assigner',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_task_assignment',
                    'payload' => [
                        'band_space_id' => (string) $bandSpace->id,
                        'task_id' => (string) $task->id,
                        'task_title' => 'Titre actuel',
                        'actor_id' => 'actor-1',
                        'actor_username' => 'assigner',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_task_mention_notification_refreshes_to_the_live_task_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Titre actuel'])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::TaskMention,
            'payload' => [
                'band_space_id' => (string) $bandSpace->id,
                'task_id' => (string) $task->id,
                'task_title' => 'Ancien titre',
                'comment_id' => 'comment-1',
                'actor_id' => 'actor-1',
                'actor_username' => 'mentioner',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'task_mention',
                    'payload' => [
                        'band_space_id' => (string) $bandSpace->id,
                        'task_id' => (string) $task->id,
                        'task_title' => 'Titre actuel',
                        'comment_id' => 'comment-1',
                        'actor_id' => 'actor-1',
                        'actor_username' => 'mentioner',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    public function test_agenda_entry_notification_refreshes_to_the_live_title_and_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Titre actuel',
            'eventDatetime' => new \DateTimeImmutable('2026-08-15T20:00:00+00:00'),
        ])->create();
        $notification = NotificationFactory::new([
            'recipient' => $user,
            'type' => NotificationType::BandSpaceAgendaEntryCreated,
            'payload' => [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => 'The Rockers',
                'agenda_entry_id' => (string) $entry->id,
                'entry_title' => 'Ancien titre',
                'event_datetime' => '2020-01-01T10:00:00+00:00',
                'actor_id' => 'actor-1',
                'actor_username' => 'creator',
            ],
            'creationDatetime' => new \DateTimeImmutable('2026-05-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/user/notifications', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotification',
            '@id' => '/api/user/notifications',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/notifications/' . $notification->id,
                    '@type' => 'UserNotification',
                    'id' => (string) $notification->id,
                    'type' => 'band_space_agenda_entry_created',
                    'payload' => [
                        'band_space_id' => (string) $bandSpace->id,
                        'band_space_name' => 'The Rockers',
                        'agenda_entry_id' => (string) $entry->id,
                        'entry_title' => 'Titre actuel',
                        'event_datetime' => '2026-08-15T20:00:00+00:00',
                        'actor_id' => 'actor-1',
                        'actor_username' => 'creator',
                    ],
                    'read_datetime' => null,
                    'creation_datetime' => $notification->creationDatetime->format(\DATE_ATOM),
                ],
            ],
        ]);
    }

    private function notificationRepository(): NotificationRepository
    {
        return self::getContainer()->get(NotificationRepository::class);
    }
}
