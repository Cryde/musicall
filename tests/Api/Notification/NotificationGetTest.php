<?php

namespace App\Tests\Api\Notification;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class NotificationGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_notification_not_logged()
    {
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_notification()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);


        $thread = MessageThreadFactory::new()->create();
        $thread2 = MessageThreadFactory::new()->create();
        $thread3 = MessageThreadFactory::new()->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread, 'isRead' => 0])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread2, 'isRead' => 0])->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread3, 'isRead' => 1])->create();
        MessageThreadMetaFactory::new(['user' => $user2, 'thread' => $thread, 'isRead' => 0])->create();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            'unread_messages' => 2
        ]);
    }

    public function test_get_notification_with_role_admin()
    {
        $user1 = UserFactory::new()->asAdminUser()->create()->object();

        $thread = MessageThreadFactory::new()->create();
        MessageThreadMetaFactory::new(['user' => $user1, 'thread' => $thread, 'isRead' => 0])->create();
        PublicationFactory::new(['status' => Publication::STATUS_PENDING,])->create();
        PublicationFactory::new(['status' => Publication::STATUS_ONLINE,])->create(); // not taken into count
        PublicationFactory::new(['status' => Publication::STATUS_DRAFT,])->create(); // not taken into count
        GalleryFactory::new(['status' => Gallery::STATUS_PENDING])->create();
        GalleryFactory::new(['status' => Gallery::STATUS_DRAFT])->create();// not taken into count
        GalleryFactory::new(['status' => Gallery::STATUS_ONLINE])->create();// not taken into count

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            'unread_messages'      => 1,
            'pending_galleries'    => 1,
            'pending_publications' => 1,
        ]);
    }
}