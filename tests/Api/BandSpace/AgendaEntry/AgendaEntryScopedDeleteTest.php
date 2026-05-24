<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryExceptionRepository;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceActivityRepository;
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
class AgendaEntryScopedDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    // ---- Single occurrence --------------------------------------------------

    public function test_delete_single_occurrence_creates_exception_and_skips_in_aggregate(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/occurrences/2026-06-15',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Exception row persisted - clear then re-fetch BandSpace so passing it
        // as a Doctrine query parameter keeps a valid identifier.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $reloaded = $repo->findOneByIdAndBandSpace($entryId, $reloadedBand);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->exceptions);
        $this->assertSame('2026-06-15', $reloaded->exceptions[0]->occurrenceDate->format('Y-m-d'));

        // Aggregator skips the cancelled date.
        $aggregator = self::getContainer()->get(AgendaAggregator::class);
        $items = $aggregator->aggregate(
            $reloadedBand,
            new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-06-30 23:59:59', new DateTimeZone('UTC')),
        );
        $dates = array_map(static fn ($i) => substr($i->datetime, 0, 10), $items);
        $this->assertContains('2026-06-01', $dates, 'first occurrence still present');
        $this->assertContains('2026-06-08', $dates, 'occurrence before the cancelled one still present');
        $this->assertNotContains('2026-06-15', $dates, 'cancelled occurrence must be filtered out');
        $this->assertContains('2026-06-22', $dates, 'occurrence after the cancelled one still present');

        // Activity recorded.
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('occurrence_cancelled', $activities[0]->type);
        $this->assertSame('2026-06-15', $activities[0]->payload['occurrence_date']);
    }

    public function test_delete_single_occurrence_is_idempotent(): void
    {
        // Seed an existing exception so a single API call exercises the "row
        // already exists" branch - loginUser persists for one jsonRequest only,
        // so two API calls in one test would fail auth on the second.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $existing = new \App\Entity\BandSpace\AgendaEntryException();
        $existing->agendaEntry = $entry;
        $existing->occurrenceDate = new DateTimeImmutable('2026-06-15');
        $em->persist($existing);
        $em->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/occurrences/2026-06-15',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $em->clear();
        $exceptionRepo = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertCount(1, $exceptionRepo->findBy(['agendaEntry' => $entryId]), 'no duplicate row created');
    }

    public function test_delete_single_occurrence_on_non_recurring_entry_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/2026-06-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Cet événement n'est pas récurrent",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Cet événement n'est pas récurrent",
        ]);
    }

    public function test_delete_single_occurrence_invalid_date_returns_400(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/not-a-date',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Date d'occurrence invalide (format attendu: YYYY-MM-DD)",
            'status' => 400,
            'type' => '/errors/400',
            'description' => "Date d'occurrence invalide (format attendu: YYYY-MM-DD)",
        ]);
    }

    public function test_delete_single_occurrence_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $owner,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/2026-06-15',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    // ---- This + future ------------------------------------------------------

    public function test_delete_from_occurrence_truncates_recurrence_until_date(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/from/2026-06-22',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $reloaded = $repo->findOneByIdAndBandSpace($entryId, $reloadedBand);
        $this->assertNotNull($reloaded);
        $this->assertSame('2026-06-21', $reloaded->recurrenceUntilDate->format('Y-m-d'));

        $aggregator = self::getContainer()->get(AgendaAggregator::class);
        $items = $aggregator->aggregate(
            $reloadedBand,
            new DateTimeImmutable('2026-06-01 00:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-07-31 23:59:59', new DateTimeZone('UTC')),
        );
        $dates = array_map(static fn ($i) => substr($i->datetime, 0, 10), $items);
        $this->assertContains('2026-06-01', $dates);
        $this->assertContains('2026-06-08', $dates);
        $this->assertContains('2026-06-15', $dates);
        $this->assertNotContains('2026-06-22', $dates, 'picked occurrence must be gone');
        $this->assertNotContains('2026-06-29', $dates, 'subsequent occurrences must be gone');

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('series_truncated', $activities[0]->type);
        $this->assertSame('2026-06-22', $activities[0]->payload['from_occurrence_date']);
        $this->assertSame('2026-06-21', $activities[0]->payload['recurrence_until_date']);
    }

    public function test_delete_from_first_occurrence_removes_entire_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/from/2026-06-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $bandSpace));

        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
    }

    public function test_delete_from_occurrence_on_non_recurring_entry_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/from/2026-06-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Cet événement n'est pas récurrent",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Cet événement n'est pas récurrent",
        ]);
    }

    public function test_delete_from_occurrence_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $owner,
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/from/2026-06-22',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }
}
