<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Agenda;

use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AgendaRecurrenceMonthlyMode;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use DateTimeZone;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A recurring series must keep the wall clock time the band typed, on both sides of a daylight
 * saving change.
 *
 * Datetimes are stored as UTC instants, so 20:00 in Paris is 19:00Z in winter and 18:00Z in summer.
 * Stepping the rule in UTC preserves the UTC time of day instead of the wall clock, which is what
 * turned a 20:00 rehearsal anchored in February into a 21:00 rehearsal for the whole rest of the
 * year. Every window below straddles a boundary, so the expected UTC times deliberately differ
 * inside one response while the Paris time never does.
 *
 * Europe switched to summer time on 29 March 2026 and back on 25 October 2026.
 */
#[ResetDatabase]
class AgendaRecurrenceDstTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_weekly_series_keeps_its_local_time_when_the_clocks_go_forward(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Monday 2 February 2026, 20:00 in Paris, which is 19:00Z while winter time is in force.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-02-02 19:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-23&to=2026-04-07',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-02-02T19:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Répétition',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                // 20:00 Paris on all three, one hour of UTC apart because the clocks moved between
                // the first and the second.
                $member('20260323-1900', '2026-03-23T19:00:00+00:00'),
                $member('20260330-1800', '2026-03-30T18:00:00+00:00'),
                $member('20260406-1800', '2026-04-06T18:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-23&to=2026-04-07',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_weekly_series_keeps_its_local_time_when_the_clocks_go_back(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Monday 3 August 2026, 20:00 in Paris, which is 18:00Z while summer time is in force.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-08-03 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-10-19&to=2026-11-03',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-08-03T18:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Répétition',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $member('20261019-1800', '2026-10-19T18:00:00+00:00'),
                $member('20261026-1900', '2026-10-26T19:00:00+00:00'),
                $member('20261102-1900', '2026-11-02T19:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-10-19&to=2026-11-03',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_daily_series_keeps_its_local_time_across_the_switch_night(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 27 March 2026, 20:00 in Paris: the series runs straight through the night of the switch.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Résidence',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-03-27 19:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Daily,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-04-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-28&to=2026-03-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'daily',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-04-30',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-03-27T19:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Résidence',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [
                $member('20260328-1900', '2026-03-28T19:00:00+00:00'),
                // The night of 28 to 29 March is the short one: still 20:00 in Paris.
                $member('20260329-1800', '2026-03-29T18:00:00+00:00'),
                $member('20260330-1800', '2026-03-30T18:00:00+00:00'),
                // On the `to` day itself: the bound is inclusive and runs to the end of its day.
                $member('20260331-1800', '2026-03-31T18:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-28&to=2026-03-31',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * The hour between 02:00 and 03:00 does not exist on 29 March, so an occurrence landing in it
     * has no valid time and PHP pushes it to 03:30. That much is unavoidable. What must not happen
     * is the shift outliving the night: measuring each occurrence from the one before it would
     * carry the pushed hour into every occurrence for the rest of the series.
     */
    public function test_a_series_anchored_in_the_skipped_hour_recovers_after_the_switch_night(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 02:30 in Paris, the time the clocks skip on 29 March.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Veille',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-03-27 01:30:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Daily,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-04-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-28&to=2026-04-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'daily',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-04-30',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-03-27T01:30:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Veille',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => [
                // 02:30 Paris, winter time.
                $member('20260328-0130', '2026-03-28T01:30:00+00:00'),
                // The one occurrence with no valid time: 03:30 Paris, the only hour available.
                $member('20260329-0130', '2026-03-29T01:30:00+00:00'),
                // Back to 02:30 Paris, now summer time, and it stays there. The last one is on the
                // `to` day itself, which an inclusive bound reaches to the end of.
                $member('20260330-0030', '2026-03-30T00:30:00+00:00'),
                $member('20260331-0030', '2026-03-31T00:30:00+00:00'),
                $member('20260401-0030', '2026-04-01T00:30:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-28&to=2026-04-01',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_monthly_by_date_series_keeps_its_local_time_when_the_clocks_go_forward(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // The 15th of every month at 20:00 in Paris, anchored in January.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Réunion',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-15 19:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Monthly,
            'recurrenceMonthlyMode' => AgendaRecurrenceMonthlyMode::ByDate,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-01&to=2026-06-01',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_monthly_mode' => 'by_date',
            'recurrence_until_date' => '2026-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-01-15T19:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Réunion',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $member('20260315-1900', '2026-03-15T19:00:00+00:00'),
                $member('20260415-1800', '2026-04-15T18:00:00+00:00'),
                $member('20260515-1800', '2026-05-15T18:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-01&to=2026-06-01',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_monthly_by_weekday_series_keeps_its_local_time_when_the_clocks_go_back(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 2026-06-02 is the first Tuesday of June, 20:00 in Paris.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Conseil',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-06-02 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Monthly,
            'recurrenceMonthlyMode' => AgendaRecurrenceMonthlyMode::ByWeekday,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-10-01&to=2026-11-04',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_monthly_mode' => 'by_weekday',
            'recurrence_until_date' => '2026-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-06-02T18:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Conseil',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $member('20261006-1800', '2026-10-06T18:00:00+00:00'),
                $member('20261103-1900', '2026-11-03T19:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-10-01&to=2026-11-04',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * Yearly never straddles a boundary, since a series lands on the same calendar date every year
     * and therefore in the same half of the year. The case is pinned all the same: the anchor now
     * makes a round trip through civil time, and getting that wrong would show up here first.
     */
    public function test_yearly_series_keeps_its_local_time_year_after_year(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 14 July at 20:00 in Paris, which is 18:00Z every single year.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert annuel',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-07-14 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Yearly,
            'recurrenceUntilDate' => new DateTimeImmutable('2029-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2029-12-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'yearly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2029-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-07-14T18:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => false,
                'title' => 'Concert annuel',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 4,
            'member' => [
                $member('20260714-1800', '2026-07-14T18:00:00+00:00'),
                $member('20270714-1800', '2027-07-14T18:00:00+00:00'),
                $member('20280714-1800', '2028-07-14T18:00:00+00:00'),
                $member('20290714-1800', '2029-07-14T18:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2029-12-31',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * An all-day entry is not an instant: the create and update processors pin it at UTC midnight
     * and it stands for a calendar date. Stepping it in Paris and converting back would put it at
     * 23:00 the day before as soon as winter time returns, which is exactly the off-by-one #819
     * fixed on the front end, so it stays on UTC midnight whatever the clocks do.
     */
    public function test_all_day_series_stays_on_utc_midnight_across_the_switch(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Tournée',
            'description' => null,
            'location' => null,
            'isAllDay' => true,
            'eventDatetime' => new DateTimeImmutable('2026-03-23 00:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-23&to=2026-04-07',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-12-31',
            'series_id' => $entry->id,
            'series_start_datetime' => '2026-03-23T00:00:00+00:00',
        ];
        $member = function (string $occKey, string $datetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => null,
                'is_all_day' => true,
                'title' => 'Tournée',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $member('20260323-0000', '2026-03-23T00:00:00+00:00'),
                $member('20260330-0000', '2026-03-30T00:00:00+00:00'),
                $member('20260406-0000', '2026-04-06T00:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-03-23&to=2026-04-07',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }
}
