<?php

namespace App\Tests\Api\Notification;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Feedback\FeedbackFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class NotificationGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_notification_not_logged(): void
    {
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_notification(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);


        // Two unread messages in one thread, one in another, and a third thread read to the end.
        // The answer is therefore 3 messages across 2 threads: under the boolean it was 2, because it
        // counted threads and called them messages (#954).
        $twoUnread = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $twoUnread, 'author' => $user2, 'creationDatetime' => new \DateTime('2026-09-01 10:00:00')])->create();
        MessageFactory::new(['thread' => $twoUnread, 'author' => $user2, 'creationDatetime' => new \DateTime('2026-09-01 10:01:00')])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $twoUnread, 'lastReadDatetime' => null])->create();

        $oneUnread = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $oneUnread, 'author' => $user2, 'creationDatetime' => new \DateTime('2026-09-02 10:00:00')])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $oneUnread, 'lastReadDatetime' => null])->create();

        $caughtUp = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $caughtUp, 'author' => $user2, 'creationDatetime' => new \DateTime('2026-09-03 10:00:00')])->create();
        MessageThreadMetaFactory::new([
            'user' => $user1,
            'thread' => $caughtUp,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-03 10:00:01'),
        ])->create();

        // Somebody else's unread message in a thread of their own, which must not leak into the count.
        $otherPersons = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $otherPersons, 'author' => $user1, 'creationDatetime' => new \DateTime('2026-09-04 10:00:00')])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $otherPersons, 'lastReadDatetime' => null])->create();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Notification',
            '@id' => '/api/notifications',
            '@type' => 'Notification',
            'unread_messages' => 3
        ]);
    }

    public function test_get_notification_with_role_admin(): void
    {
        $user1 = UserFactory::new()->asAdminUser()->create();

        $thread = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $thread, 'author' => UserFactory::new()->asBaseUser()])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread, 'lastReadDatetime' => null])->create();
        PublicationFactory::new(['status' => Publication::STATUS_PENDING,])->create();
        PublicationFactory::new(['status' => Publication::STATUS_ONLINE,])->create(); // not taken into count
        PublicationFactory::new(['status' => Publication::STATUS_DRAFT,])->create(); // not taken into count
        GalleryFactory::new(['status' => Gallery::STATUS_PENDING])->create();
        GalleryFactory::new(['status' => Gallery::STATUS_DRAFT])->create();// not taken into count
        GalleryFactory::new(['status' => Gallery::STATUS_ONLINE])->create();// not taken into count
        FeedbackFactory::new()->create();
        FeedbackFactory::new()->asTriaged()->create(); // not taken into count

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Notification',
            '@id' => '/api/notifications',
            '@type' => 'Notification',
            'unread_messages'      => 1,
            'pending_galleries'    => 1,
            'pending_publications' => 1,
            'new_feedbacks'        => 1,
        ]);
    }

    public function test_get_notification_with_role_admin_and_no_notifications(): void
    {
        $user1 = UserFactory::new()->asAdminUser()->create();

        $thread = MessageThreadFactory::new()->create();
        MessageFactory::new(['thread' => $thread, 'author' => UserFactory::new()->asBaseUser()])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread, 'lastReadDatetime' => null])->create();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Notification',
            '@id' => '/api/notifications',
            '@type' => 'Notification',
            'unread_messages'      => 1,
            'pending_galleries'    => 0,
            'pending_publications' => 0,
            'new_feedbacks'        => 0,
        ]);
    }
}
