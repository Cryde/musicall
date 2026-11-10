<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Messenger\SendPushNotification;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
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
            'ask_availability' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $creator->id,
            'creator_username' => $creatorUsername,
            'creator_display_name' => $creatorUsername,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $expectedPayload = [
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'The Rockers',
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => 'Répétition générale',
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
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

    public function test_creating_an_all_day_entry_says_so_in_the_notification(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $creatorId = (string) $creator->id;
        $creatorUsername = $creator->username;

        $this->client->loginUser($creator);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/agenda-entries',
            ['title' => 'Festival', 'eventDatetime' => '2026-06-15', 'isAllDay' => true],
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
            'title' => 'Festival',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T00:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => true,
            'ask_availability' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $creator->id,
            'creator_username' => $creatorUsername,
            'creator_display_name' => $creatorUsername,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // The entry is pinned to UTC midnight, which reads as the day before for a member in
        // Martinique unless the payload says the datetime is a written day (#877).
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($alice, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceAgendaEntryCreated, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'The Rockers',
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => 'Festival',
            'event_datetime' => '2026-06-15T00:00:00+00:00',
            'is_all_day' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'actor_id' => $creatorId,
            'actor_username' => $creatorUsername,
        ], $notifications[0]->payload);
    }

    public function test_creating_a_recurring_entry_says_it_repeats_in_one_notification_per_member(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com', 'locale' => 'en']);
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
            [
                'title' => 'Répétition',
                'eventDatetime' => '2027-01-06T19:00:00+00:00',
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => '2027-06-30',
            ],
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
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'event_datetime' => '2027-01-06T19:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'ask_availability' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2027-06-30',
            'recurrence_monthly_mode' => null,
            'creator_id' => $creator->id,
            'creator_username' => $creatorUsername,
            'creator_display_name' => $creatorUsername,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // The series is one entry, so each member gets one notification for all its dates, and that
        // notification has to say the entry repeats or it reads as a single date.
        $expectedPayload = [
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'The Rockers',
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => 'Répétition',
            'event_datetime' => '2027-01-06T19:00:00+00:00',
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2027-06-30',
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

        // One push per member too, each in the member's own language.
        $data = ['type' => 'band_space_agenda_entry_created', 'route' => '/band/' . $bandSpaceId . '/agenda'];
        $expectedPushes = [
            [(string) $alice->id, 'The Rockers', $creatorUsername . ' a ajouté l\'événement hebdomadaire « Répétition »', $data],
            [(string) $bob->id, 'The Rockers', $creatorUsername . ' added the weekly event "Répétition"', $data],
        ];
        $pushes = array_map(
            static fn (SendPushNotification $push): array => [$push->userId, $push->title, $push->body, $push->data],
            $this->queuedPushes(),
        );
        sort($expectedPushes);
        sort($pushes);
        $this->assertSame($expectedPushes, $pushes);
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

    /** @return list<SendPushNotification> */
    private function queuedPushes(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
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
