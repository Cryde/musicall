<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AgendaEntryNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_creating_an_entry_notifies_other_active_members(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $creatorId = (string) $creator->id;
        $creatorUsername = $creator->username;

        $this->client->loginUser($creator);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/agenda-entries',
            ['title' => 'Répétition générale', 'eventDatetime' => '2026-06-15T20:00:00+00:00'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entry = self::getContainer()->get(AgendaEntryRepository::class)->findByBandSpace($bandSpace)[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpaceId,
            'title' => 'Répétition générale',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $creator->id,
            'creator_username' => $creatorUsername,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $expectedPayload = [
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'The Rockers',
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => 'Répétition générale',
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'actor_id' => $creatorId,
            'actor_username' => $creatorUsername,
        ];
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        foreach ([$alice, $bob] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::BandSpaceAgendaEntryCreated, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        // The creator is never notified of their own entry.
        $this->assertCount(0, $notificationRepository->findForRecipient($creator, 10, 0));
    }

    public function test_creating_an_entry_in_a_solo_band_space_notifies_no_one(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();

        $this->client->loginUser($creator);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            ['title' => 'Répétition', 'eventDatetime' => '2026-06-15T20:00:00+00:00'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Creator is the only active member: the listener's empty-recipients early-return fires.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_inactive_member_is_not_notified(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $kicked = UserFactory::new()->create(['username' => 'kicked', 'email' => 'kicked@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $kicked, 'status' => MembershipStatus::Kicked])->create();

        $this->client->loginUser($creator);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            ['title' => 'Répétition', 'eventDatetime' => '2026-06-15T20:00:00+00:00'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($kicked, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_creation(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();

        // A notification failure must never roll back or 500 the agenda creation (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($creator);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries',
            ['title' => 'Répétition', 'eventDatetime' => '2026-06-15T20:00:00+00:00'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(1, self::getContainer()->get(AgendaEntryRepository::class)->findByBandSpace($bandSpace));
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($alice, 10, 0));
    }

    private function throwingNotificationCreator(): NotificationCreator
    {
        return new readonly class extends NotificationCreator {
            public function __construct()
            {
            }

            public function create(User $recipient, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }

            public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }
        };
    }
}
