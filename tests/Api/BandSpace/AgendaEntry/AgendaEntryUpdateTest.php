<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryException;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryExceptionRepository;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\AgendaAggregator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class AgendaEntryUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_agenda_entry_title_and_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Ancien titre',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            [
                'title' => 'Nouveau titre',
                'eventDatetime' => '2026-06-20T18:30:00+00:00',
            ],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Nouveau titre',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-20T18:30:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $types = array_map(fn($a) => $a->type, $activities);
        $this->assertEqualsCanonicalizing(['title_changed', 'event_datetime_changed'], $types);
    }

    public function test_update_agenda_entry_partial_keeps_other_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'location' => 'Salle A',
            'description' => 'Description initiale',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['location' => 'Salle B'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Concert',
            'description' => 'Description initiale',
            'location' => 'Salle B',
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('location_changed', $activities[0]->type);
        $this->assertSame(['from' => 'Salle A', 'to' => 'Salle B'], $activities[0]->payload);
    }

    public function test_update_set_end_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['endDatetime' => '2026-06-15T23:00:00+00:00'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => '2026-06-15T23:00:00+00:00',
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('end_datetime_changed', $activities[0]->type);
        $this->assertSame(
            ['from' => null, 'to' => '2026-06-15T23:00:00+00:00'],
            $activities[0]->payload,
        );
    }

    public function test_update_clear_end_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-06-15 23:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['endDatetime' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Concert',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('end_datetime_changed', $activities[0]->type);
        $this->assertSame(
            ['from' => '2026-06-15T23:00:00+00:00', 'to' => null],
            $activities[0]->payload,
        );
    }

    public function test_update_rejects_end_before_start(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['endDatetime' => '2026-06-15T19:00:00+00:00'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_datetime',
                    'message' => 'La fin doit être postérieure au début',
                    'code' => '778b7ae0-84d3-481a-9dec-35fdb64b1d78',
                ],
            ],
            'detail' => 'end_datetime: La fin doit être postérieure au début',
            'type' => '/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            'title' => 'An error occurred',
            '@context' => '/api/contexts/ConstraintViolation',
            'description' => 'end_datetime: La fin doit être postérieure au début',
        ]);
    }

    public function test_update_toggle_all_day_normalizes_time(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Tournée',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 14:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-06-17 22:30:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['isAllDay' => true],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Tournée',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-15T00:00:00+00:00',
            'end_datetime' => '2026-06-17T00:00:00+00:00',
            'is_all_day' => true,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $types = array_map(fn($a) => $a->type, $activities);
        $this->assertEqualsCanonicalizing(
            ['is_all_day_changed', 'event_datetime_changed', 'end_datetime_changed'],
            $types,
        );
    }

    public function test_update_no_change_records_no_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['title' => 'Concert'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(0, $activities);
    }

    public function test_update_agenda_entry_validation_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_update_without_event_datetime_leaves_the_series_anchor_alone(): void
    {
        // Weekly rehearsal anchored Monday 5 January. Opening the 9 March occurrence to fix a typo
        // used to PATCH that occurrence's date onto the series, moving the anchor to 9 March and
        // dropping every January and February occurrence. The drawer now sends everything except
        // the start, which is what this payload is.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétiton hebdomadaire',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-05 20:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            [
                'title' => 'Répétition hebdomadaire',
                'endDatetime' => null,
                'isAllDay' => false,
                'location' => null,
                'description' => null,
                'recurrenceFrequency' => 'weekly',
                'recurrenceUntilDate' => '2026-06-30',
                'recurrenceMonthlyMode' => null,
            ],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition hebdomadaire',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-05T20:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2026-06-30',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // Read the row back rather than trusting the response: the anchor is what the expansion
        // runs from, and the entity held by the test is detached once the kernel reboots.
        $persisted = self::getContainer()->get(AgendaEntryRepository::class)->find($entry->id);
        $this->assertInstanceOf(AgendaEntry::class, $persisted);
        $this->assertSame(
            '2026-01-05T20:00:00+00:00',
            $persisted->eventDatetime->format(\DateTimeInterface::ATOM),
        );

        // The processor records an activity for every field it actually writes, so a lone
        // title_changed is a second, independent proof that the start was never touched.
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Agenda, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('title_changed', $activities[0]->type);
    }

    public function test_update_title_only_keeps_the_occurrences_before_the_edited_one(): void
    {
        // Same series, seen from the agenda: the four January occurrences must survive a title edit
        // made from a March one.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétiton hebdomadaire',
            'description' => null,
            'location' => 'Studio',
            'eventDatetime' => new DateTimeImmutable('2026-01-05 20:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['title' => 'Répétition hebdomadaire'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        // The expansion is read through the aggregator rather than through a second GET, because
        // loginUser only holds for one request and the single call is spent on the PATCH above,
        // which is the operation under test. The agenda endpoint itself is covered elsewhere.
        $bandSpace = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpace->id);
        $occurrences = self::getContainer()->get(AgendaAggregator::class)->aggregate(
            $bandSpace,
            $viewerMembership,
            new DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')),
            new DateTimeImmutable('2026-01-31 23:59:59', new \DateTimeZone('UTC')),
        );

        $this->assertSame(
            ['2026-01-05T20:00:00+00:00', '2026-01-12T20:00:00+00:00', '2026-01-19T20:00:00+00:00', '2026-01-26T20:00:00+00:00'],
            array_map(static fn($item): string => $item->datetime, $occurrences)
        );
        $this->assertSame(
            ['Répétition hebdomadaire', 'Répétition hebdomadaire', 'Répétition hebdomadaire', 'Répétition hebdomadaire'],
            array_map(static fn($item): string => $item->title, $occurrences)
        );
    }

    public function test_update_only_recurrence_until_date_extends_existing_series(): void
    {
        // Important #1 regression guard: a PATCH that touches only `recurrence_until_date`
        // (without `recurrence_frequency`) must persist on an already-recurring entry.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-04 18:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['recurrenceUntilDate' => '2026-12-31'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-04T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2026-12-31',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_only_monthly_mode_switches_mode_on_monthly_series(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Réunion mensuelle',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-05 19:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Monthly,
            'recurrenceMonthlyMode' => \App\Enum\BandSpace\AgendaRecurrenceMonthlyMode::ByDate,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['recurrenceMonthlyMode' => 'by_weekday'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Réunion mensuelle',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-05T19:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'monthly',
            'recurrence_until_date' => '2026-12-31',
            'recurrence_monthly_mode' => 'by_weekday',
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_clears_recurrence_when_frequency_set_to_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-04 18:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['recurrenceFrequency' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            '@type' => 'AgendaEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-01-04T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_moving_a_series_to_another_weekday_drops_the_cancellations_it_orphans(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // Every Monday from 1 June. 15 June is one of its occurrences, 16 June is not.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['2026-06-15', '2026-06-16'] as $cancelledDate) {
            $cancellation = new AgendaEntryException();
            $cancellation->agendaEntry = $entry;
            $cancellation->occurrenceDate = new DateTimeImmutable($cancelledDate);
            $entityManager->persist($cancellation);
        }
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            ['eventDatetime' => '2026-06-02T18:00:00+00:00'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            '@type' => 'AgendaEntry',
            'id' => $entryId,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-02T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2026-07-31',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // The series runs on Tuesdays now: the cancelled Monday points at nothing and goes, while
        // the Tuesday the rule has just started producing is a real cancellation and stays.
        $entityManager->clear();
        $cancellations = self::getContainer()->get(AgendaEntryExceptionRepository::class)
            ->findBy(['agendaEntry' => $entryId]);
        $this->assertSame(
            ['2026-06-16'],
            array_map(static fn(AgendaEntryException $e): string => $e->occurrenceDate->format('Y-m-d'), $cancellations),
        );
    }

    public function test_an_edit_that_leaves_the_rule_alone_keeps_the_cancellations(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $cancellation = new AgendaEntryException();
        $cancellation->agendaEntry = $entry;
        $cancellation->occurrenceDate = new DateTimeImmutable('2026-06-15');
        $entityManager->persist($cancellation);
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            ['title' => 'Répétition générale'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            '@type' => 'AgendaEntry',
            'id' => $entryId,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition générale',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-01T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => 'weekly',
            'recurrence_until_date' => '2026-07-31',
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $entityManager->clear();
        $cancellations = self::getContainer()->get(AgendaEntryExceptionRepository::class)
            ->findBy(['agendaEntry' => $entryId]);
        $this->assertSame(
            ['2026-06-15'],
            array_map(static fn(AgendaEntryException $e): string => $e->occurrenceDate->format('Y-m-d'), $cancellations),
        );
    }

    public function test_dropping_the_recurrence_drops_every_cancellation(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $cancellation = new AgendaEntryException();
        $cancellation->agendaEntry = $entry;
        $cancellation->occurrenceDate = new DateTimeImmutable('2026-06-15');
        $entityManager->persist($cancellation);
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            ['recurrenceFrequency' => null],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId,
            '@type' => 'AgendaEntry',
            'id' => $entryId,
            'band_space_id' => $bandSpace->id,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'event_datetime' => '2026-06-01T18:00:00+00:00',
            'end_datetime' => null,
            'is_all_day' => false,
            'recurrence_frequency' => null,
            'recurrence_until_date' => null,
            'recurrence_monthly_mode' => null,
            'creator_id' => $user->id,
            'creator_username' => $user->username,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
        ]);

        // A single event has nothing to cancel, so the rows would never be read again and would
        // come back to life the day someone turns the repetition on again.
        $entityManager->clear();
        $this->assertSame(
            [],
            self::getContainer()->get(AgendaEntryExceptionRepository::class)->findBy(['agendaEntry' => $entryId]),
        );
    }

    public function test_update_agenda_entry_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $entry = AgendaEntryFactory::new(['bandSpace' => $bandSpace, 'creator' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
