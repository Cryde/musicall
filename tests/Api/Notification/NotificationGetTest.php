<?php

namespace App\Tests\Api\Notification;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Gallery;
use App\Entity\Publication;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
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
            'unread_messages' => 3,
            'band_space_chat_unread' => []
        ]);
    }

    public function test_the_chat_unread_is_keyed_by_band_space(): void
    {
        // The sidebar badge (#962). Keyed by space so a member of several bands sees each band's own
        // number rather than a sum, and a space with nothing unread is absent rather than zero.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $writer = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);

        $loud = $this->bandWithChannel($member, $writer, 2);
        $quiet = $this->bandWithChannel($member, $writer, 0);
        $chatty = $this->bandWithChannel($member, $writer, 1);

        $this->client->loginUser($member);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/notifications');

        $this->assertResponseIsSuccessful();

        // One grouped query for every space the member belongs to, not one per space. This endpoint is
        // on a five minute timer for every signed-in user, so a per-space query here would be the
        // expensive kind of mistake.
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');
        $channelQueries = array_filter(
            $profile->getCollector('db')->getQueries()['default'] ?? [],
            static fn (array $query): bool => str_contains((string) $query['sql'], 'band_space_membership'),
        );
        $this->assertCount(1, $channelQueries);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Notification',
            '@id' => '/api/notifications',
            '@type' => 'Notification',
            'unread_messages' => 0,
            'band_space_chat_unread' => [(string) $loud->id => 2, (string) $chatty->id => 1],
        ]);
        $this->assertNotNull($quiet->id);
    }

    private function bandWithChannel(User $member, User $writer, int $unreadMessages): BandSpace
    {
        $space = BandSpaceFactory::new()->create();
        foreach ([$member, $writer] as $user) {
            BandSpaceMembershipFactory::new([
                'bandSpace' => $space,
                'user' => $user,
                'creationDatetime' => new \DateTime('2026-09-01 09:00:00'),
            ])->create();
        }

        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        for ($index = 1; $index <= $unreadMessages; ++$index) {
            MessageFactory::new([
                'thread' => $channel,
                'author' => $writer,
                'creationDatetime' => new \DateTime(sprintf('2026-09-02 10:%02d:00', $index)),
            ])->create();
        }
        MessageThreadMetaFactory::new(['thread' => $channel, 'user' => $member, 'lastReadDatetime' => null])->create();

        return $space;
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
            'band_space_chat_unread' => [],
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
            'band_space_chat_unread' => [],
        ]);
    }
}
