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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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
        // Reloaded for the same reason as the band space: the clear above detached it, and the
        // aggregator binds it as a query parameter to filter personal finance entries.
        $reloadedMembership = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceMembershipRepository::class)->find((string) $viewerMembership->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $reloaded = $repo->findOneByIdAndBandSpace($entryId, $reloadedBand);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->exceptions);
        $this->assertSame('2026-06-15', $reloaded->exceptions[0]->occurrenceDate->format('Y-m-d'));

        // Aggregator skips the cancelled date.
        $aggregator = self::getContainer()->get(AgendaAggregator::class);
        $items = $aggregator->aggregate(
            $reloadedBand,
            $reloadedMembership,
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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

    public function test_cancelling_the_last_live_occurrence_deletes_the_series(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // Three Mondays: 1, 8 and 15 June. The first two are already cancelled.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-15'),
        ])->create();
        $entryId = $entry->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['2026-06-01', '2026-06-08'] as $cancelledDate) {
            $cancellation = new \App\Entity\BandSpace\AgendaEntryException();
            $cancellation->agendaEntry = $entry;
            $cancellation->occurrenceDate = new DateTimeImmutable($cancelledDate);
            $entityManager->persist($cancellation);
        }
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/occurrences/2026-06-15',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // A series with nothing left to expand renders nowhere, and the expanded agenda is the only
        // place it could be reached from, so it goes instead of being left unreachable.
        $entityManager->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $reloadedBand));
        $exceptionRepo = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertSame([], $exceptionRepo->findBy(['agendaEntry' => $entryId]));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
        $this->assertSame(['title' => 'Répétition'], $activities[0]->payload);
    }

    public function test_cancelling_an_occurrence_of_an_endless_series_keeps_the_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // No horizon, so the rule has no finite list of occurrences and can never be cancelled down
        // to nothing. ValidRecurrence forbids writing that through the API; the guard is here so a
        // row that ever escaped it is left alone rather than deleted for being unenumerable.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => null,
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNotNull($repo->findOneByIdAndBandSpace($entryId, $reloadedBand));
        $exceptionRepo = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertCount(1, $exceptionRepo->findBy(['agendaEntry' => $entryId]));
    }

    public function test_delete_single_occurrence_on_non_recurring_entry_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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

    public function test_delete_single_occurrence_with_a_null_byte_is_a_bad_request(): void
    {
        // #934. A path segment carries a null byte all the way through, unlike a query string, so the
        // serializer decorator cannot help here: this processor reads the raw segment itself. It used
        // to parse with createFromFormat, which raises a ValueError rather than returning false, and
        // a ValueError is not an Exception, so the request ended as a 500.
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
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/2026-06-15%00',
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

        $repository = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertCount(1, $repository->findByBandSpace($bandSpace));
    }

    public function test_truncating_a_series_with_a_null_byte_is_a_bad_request(): void
    {
        // #934 again, on the sibling endpoint. Same processor shape, same raw path segment, so it
        // needed the same fix rather than inheriting one.
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
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/from/2026-06-15%00',
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

        $repository = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertCount(1, $repository->findByBandSpace($bandSpace));
    }

    public function test_delete_single_occurrence_with_a_negative_year_is_a_bad_request(): void
    {
        // A negative year round trips through the loose parser byte for byte, so the round trip alone
        // waves it through where createFromFormat refused it. It then reaches a DATE column that
        // rejects it, and the request ends as the very 500 this branch exists to remove. The anchored
        // shape check in CalendarDay is what keeps the accept set where it was.
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
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/-0001-11-30',
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

        $repository = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertCount(1, $repository->findByBandSpace($bandSpace));
    }

    public function test_truncating_a_series_with_a_negative_year_does_not_delete_it(): void
    {
        // Same widening on the sibling endpoint, with a quieter symptom: a negative year sorts before
        // the first occurrence, so instead of crashing this one silently deleted the whole series.
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
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/from/-0001-11-30',
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

        $repository = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertCount(1, $repository->findByBandSpace($bandSpace));
    }

    public function test_delete_single_occurrence_with_year_zero_is_a_bad_request(): void
    {
        // Narrower than the negative year but the same root cause: `0000-01-01` round trips through
        // the loose parser untouched, so a round trip based guard accepted it while `Assert\Date`
        // refused it, because checkdate() will not have year zero. There is no constraint behind a
        // path segment to catch it either, so the request answered 204 and left an exception row
        // matching no occurrence. CalendarDay calls checkdate() itself now.
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
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entry->id . '/occurrences/0000-01-01',
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

        // The orphan row is the part that used to be invisible: a 204 and a cancellation matching no
        // occurrence at all.
        $exceptionRepository = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertCount(0, $exceptionRepository->findAll());
    }

    public function test_delete_single_occurrence_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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
        // Reloaded for the same reason as the band space: the clear above detached it, and the
        // aggregator binds it as a query parameter to filter personal finance entries.
        $reloadedMembership = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceMembershipRepository::class)->find((string) $viewerMembership->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $reloaded = $repo->findOneByIdAndBandSpace($entryId, $reloadedBand);
        $this->assertNotNull($reloaded);
        $this->assertSame('2026-06-21', $reloaded->recurrenceUntilDate->format('Y-m-d'));

        $aggregator = self::getContainer()->get(AgendaAggregator::class);
        $items = $aggregator->aggregate(
            $reloadedBand,
            $reloadedMembership,
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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
        // Reloaded for the same reason as the band space: the clear above detached it, and the
        // aggregator binds it as a query parameter to filter personal finance entries.
        $reloadedMembership = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceMembershipRepository::class)->find((string) $viewerMembership->id);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
    }

    public function test_truncating_a_series_drops_the_cancellations_past_the_new_horizon(): void
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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['2026-06-15', '2026-07-20'] as $cancelledDate) {
            $cancellation = new \App\Entity\BandSpace\AgendaEntryException();
            $cancellation->agendaEntry = $entry;
            $cancellation->occurrenceDate = new DateTimeImmutable($cancelledDate);
            $entityManager->persist($cancellation);
        }
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/from/2026-07-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The series now stops on 30 June, so the July cancellation points at nothing. Left behind,
        // it would silently re-apply itself the day someone pushes the horizon back out.
        $entityManager->clear();
        $exceptionRepo = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertSame(
            ['2026-06-15'],
            array_map(
                static fn(\App\Entity\BandSpace\AgendaEntryException $e): string => $e->occurrenceDate->format('Y-m-d'),
                $exceptionRepo->findBy(['agendaEntry' => $entryId]),
            ),
        );
    }

    /**
     * Dropping a stale cancellation and deleting the whole entry both happen inside this one call:
     * the reconciler schedules a single AgendaEntryException for removal, and the entry is then
     * removed too, which cascades onto that same row. Doctrine tolerates the double scheduling, but
     * nothing else in the suite exercises the two together, so a refactor of either the reconciler
     * or this processor could reintroduce an orphaned row or an error with no test noticing.
     */
    public function test_truncating_deletes_the_series_while_dropping_a_cancellation_it_orphans(): void
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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // The first three survive the truncation and cancel everything it leaves; 20 July falls
        // outside the shortened rule and is the row the reconciler has to drop.
        foreach (['2026-06-01', '2026-06-08', '2026-06-15', '2026-07-20'] as $cancelledDate) {
            $cancellation = new \App\Entity\BandSpace\AgendaEntryException();
            $cancellation->agendaEntry = $entry;
            $cancellation->occurrenceDate = new DateTimeImmutable($cancelledDate);
            $entityManager->persist($cancellation);
        }
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/from/2026-06-22',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $entityManager->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $reloadedBand));
        $exceptionRepo = self::getContainer()->get(AgendaEntryExceptionRepository::class);
        $this->assertSame([], $exceptionRepo->findBy(['agendaEntry' => $entryId]), 'no cancellation row outlives its entry');

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
    }

    public function test_truncating_onto_an_entirely_cancelled_tail_deletes_the_series(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // Mondays from 1 June, and every one that survives the truncation below is cancelled.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable('2026-06-01 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-07-31'),
        ])->create();
        $entryId = $entry->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['2026-06-01', '2026-06-08', '2026-06-15'] as $cancelledDate) {
            $cancellation = new \App\Entity\BandSpace\AgendaEntryException();
            $cancellation->agendaEntry = $entry;
            $cancellation->occurrenceDate = new DateTimeImmutable($cancelledDate);
            $entityManager->persist($cancellation);
        }
        $entityManager->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-entries/' . $entryId . '/from/2026-06-22',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Truncating on 22 June leaves 1, 8 and 15 June, all three already cancelled: the series
        // expands to nothing and would be as unreachable as one cancelled occurrence by occurrence.
        $entityManager->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find((string) $bandSpace->id);
        $repo = self::getContainer()->get(AgendaEntryRepository::class);
        $this->assertNull($repo->findOneByIdAndBandSpace($entryId, $reloadedBand));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Agenda, $entryId);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_deleted', $activities[0]->type);
    }

    public function test_delete_from_occurrence_on_non_recurring_entry_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
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
