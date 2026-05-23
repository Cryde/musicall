<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Agenda;

use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use Doctrine\Common\Collections\ArrayCollection;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class AgendaGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_aggregates_mixed_sources_in_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $financeCategory = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Logistique'])->create();

        $manual = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => 'Préparer le set',
            'location' => 'Studio',
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Acheter cordes',
            'description' => null,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'dueDate' => new DateTimeImmutable('2026-06-20 12:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $finance = FinanceEntryFactory::new([
            'category' => $financeCategory,
            'label' => 'Location salle',
            'date' => new \DateTime('2026-06-25', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $manualId = 'manual-' . $manual->id;
        $taskId = 'task-' . $task->id;
        $financeId = 'finance-' . $finance->id;
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/agenda_items/id=' . $manualId . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'AgendaItem',
                    'id' => $manualId,
                    'band_space_id' => $bandSpace->id,
                    'source' => 'manual',
                    'source_id' => $manual->id,
                    'datetime' => '2026-06-15T20:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'title' => 'Répétition',
                    'description' => 'Préparer le set',
                    'metadata' => [
                        'location' => 'Studio',
                        'is_recurring_occurrence' => false,
                        'recurrence_frequency' => null,
                        'recurrence_monthly_mode' => null,
                        'recurrence_until_date' => null,
                        'series_id' => null,
                    ],
                ],
                [
                    '@id' => '/api/agenda_items/id=' . $taskId . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'AgendaItem',
                    'id' => $taskId,
                    'band_space_id' => $bandSpace->id,
                    'source' => 'task',
                    'source_id' => $task->id,
                    'datetime' => '2026-06-20T12:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'title' => 'Acheter cordes',
                    'description' => null,
                    'metadata' => [
                        'status' => 'todo',
                        'priority' => 'high',
                        'category_name' => null,
                        'assignees' => [],
                    ],
                ],
                [
                    '@id' => '/api/agenda_items/id=' . $financeId . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'AgendaItem',
                    'id' => $financeId,
                    'band_space_id' => $bandSpace->id,
                    'source' => 'finance',
                    'source_id' => $finance->id,
                    'datetime' => '2026-06-25T00:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'title' => 'Location salle',
                    'description' => null,
                    'metadata' => [
                        'type' => $finance->type->value,
                        'status' => $finance->status->value,
                        'scope' => $finance->scope->value,
                        'amount' => $finance->amount,
                        'amount_min' => null,
                        'amount_max' => null,
                        'category_name' => 'Logistique',
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_task_metadata_includes_assignees(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $assignee = UserFactory::new()->create(['username' => 'drummer_42', 'email' => 'drummer@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assignee])->create();

        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Acheter cordes',
            'description' => null,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Normal,
            'dueDate' => new DateTimeImmutable('2026-06-20 12:00:00', new \DateTimeZone('UTC')),
            'assignees' => new ArrayCollection([$assignee]),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $taskId = 'task-' . $task->id;
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/agenda_items/id=' . $taskId . ';bandSpaceId=' . $bandSpace->id,
                    '@type' => 'AgendaItem',
                    'id' => $taskId,
                    'band_space_id' => $bandSpace->id,
                    'source' => 'task',
                    'source_id' => $task->id,
                    'datetime' => '2026-06-20T12:00:00+00:00',
                    'end_datetime' => null,
                    'is_all_day' => false,
                    'title' => 'Acheter cordes',
                    'description' => null,
                    'metadata' => [
                        'status' => 'todo',
                        'priority' => 'normal',
                        'category_name' => null,
                        'assignees' => [
                            [
                                'id' => $assignee->id,
                                'username' => 'drummer_42',
                                'profile_picture_url' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_excludes_done_and_archived_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Done task',
            'status' => TaskStatus::Done,
            'dueDate' => new DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC')),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Archived task',
            'status' => TaskStatus::Todo,
            'archiveDatetime' => new DateTimeImmutable('2026-06-01'),
            'dueDate' => new DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC')),
        ])->create();
        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'No due date',
            'status' => TaskStatus::Todo,
            'dueDate' => null,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => $response['view'],
        ]);
    }

    public function test_excludes_items_outside_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-05-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();
        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-08-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();
        $inWindow = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $payload = $this->getResponseAsArray();
        $this->assertSame(1, $payload['totalItems']);
        $this->assertSame('manual-' . $inWindow->id, $payload['member'][0]['id']);
    }

    public function test_includes_multi_day_event_overlapping_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Starts before window, ends inside → should appear
        $startsBefore = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Starts before',
            'eventDatetime' => new DateTimeImmutable('2026-05-28 00:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-06-02 00:00:00', new \DateTimeZone('UTC')),
            'isAllDay' => true,
        ])->create();
        // Starts inside, ends after window → should appear
        $endsAfter = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Ends after',
            'eventDatetime' => new DateTimeImmutable('2026-06-28 00:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-07-03 00:00:00', new \DateTimeZone('UTC')),
            'isAllDay' => true,
        ])->create();
        // Fully spans the window → should appear
        $fullyContains = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Fully spans',
            'eventDatetime' => new DateTimeImmutable('2026-05-15 00:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-07-15 00:00:00', new \DateTimeZone('UTC')),
            'isAllDay' => true,
        ])->create();
        // Fully before window → must NOT appear
        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Fully before',
            'eventDatetime' => new DateTimeImmutable('2026-05-15 00:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-05-20 00:00:00', new \DateTimeZone('UTC')),
            'isAllDay' => true,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $payload = $this->getResponseAsArray();
        $this->assertSame(3, $payload['totalItems']);
        $ids = array_column($payload['member'], 'id');
        $this->assertContains('manual-' . $startsBefore->id, $ids);
        $this->assertContains('manual-' . $endsAfter->id, $ids);
        $this->assertContains('manual-' . $fullyContains->id, $ids);
    }

    public function test_excludes_other_band_items(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        AgendaEntryFactory::new([
            'bandSpace' => $otherBand,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 20:00:00', new \DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => $response['view'],
        ]);
    }

    public function test_default_window_when_no_params(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tomorrow = new DateTimeImmutable('tomorrow noon');
        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Demain',
            'eventDatetime' => $tomorrow,
        ])->create();

        $farFuture = (new DateTimeImmutable('today'))->modify('+90 days');
        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Trop loin',
            'eventDatetime' => $farFuture,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $payload = $this->getResponseAsArray();
        $this->assertSame(1, $payload['totalItems']);
        $this->assertSame('Demain', $payload['member'][0]['title']);
    }

    public function test_weekly_recurrence_expanded_within_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // First occurrence is before the window; rule must still produce occurrences inside it.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => 'Studio',
            'eventDatetime' => new DateTimeImmutable('2026-01-04 18:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-02-28'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-15&to=2026-02-15',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        // `to=2026-02-15` parses to 00:00:00; the 18:00 occurrence on that day lies past it.
        // Expansions inside [2026-01-15T00:00, 2026-02-15T00:00] from start 2026-01-04 weekly:
        // 2026-01-18, 2026-01-25, 2026-02-01, 2026-02-08 (all at 18:00).
        $metadata = [
            'location' => 'Studio',
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-02-28',
            'series_id' => $entry->id,
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
            'totalItems' => 4,
            'member' => [
                $member('20260118-1800', '2026-01-18T18:00:00+00:00'),
                $member('20260125-1800', '2026-01-25T18:00:00+00:00'),
                $member('20260201-1800', '2026-02-01T18:00:00+00:00'),
                $member('20260208-1800', '2026-02-08T18:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-15&to=2026-02-15',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_monthly_by_weekday_expansion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 2026-01-05 is the first Monday of January 2026.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Réunion mensuelle',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-05 19:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Monthly,
            'recurrenceMonthlyMode' => \App\Enum\BandSpace\AgendaRecurrenceMonthlyMode::ByWeekday,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-06-30'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        // First Mondays: 2026-01-05, 2026-02-02, 2026-03-02, 2026-04-06, 2026-05-04, 2026-06-01.
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_monthly_mode' => 'by_weekday',
            'recurrence_until_date' => '2026-06-30',
            'series_id' => $entry->id,
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
                'title' => 'Réunion mensuelle',
                'description' => null,
                'metadata' => $metadata,
            ];
        };
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 6,
            'member' => [
                $member('20260105-1900', '2026-01-05T19:00:00+00:00'),
                $member('20260202-1900', '2026-02-02T19:00:00+00:00'),
                $member('20260302-1900', '2026-03-02T19:00:00+00:00'),
                $member('20260406-1900', '2026-04-06T19:00:00+00:00'),
                $member('20260504-1900', '2026-05-04T19:00:00+00:00'),
                $member('20260601-1900', '2026-06-01T19:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2026-06-30',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_yearly_recurrence_clamps_feb_29(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Anniversaire bissextile',
            'description' => null,
            'location' => null,
            'isAllDay' => true,
            'eventDatetime' => new DateTimeImmutable('2024-02-29 00:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Yearly,
            'recurrenceUntilDate' => new DateTimeImmutable('2027-12-31'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2024-01-01&to=2027-12-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'yearly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2027-12-31',
            'series_id' => $entry->id,
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
                'title' => 'Anniversaire bissextile',
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
                $member('20240229-0000', '2024-02-29T00:00:00+00:00'), // leap year original
                $member('20250228-0000', '2025-02-28T00:00:00+00:00'), // clamped
                $member('20260228-0000', '2026-02-28T00:00:00+00:00'), // clamped
                $member('20270228-0000', '2027-02-28T00:00:00+00:00'), // clamped
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2024-01-01&to=2027-12-31',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_recurrence_preserves_duration_per_occurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 2-hour duration: 18:00 -> 20:00. Weekly recurrence: each occurrence must also span 2h.
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition',
            'description' => null,
            'location' => null,
            'eventDatetime' => new DateTimeImmutable('2026-01-04 18:00:00', new \DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('2026-01-04 20:00:00', new \DateTimeZone('UTC')),
            'recurrenceFrequency' => \App\Enum\BandSpace\AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable('2026-01-25'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2026-01-31',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $metadata = [
            'location' => null,
            'is_recurring_occurrence' => true,
            'recurrence_frequency' => 'weekly',
            'recurrence_monthly_mode' => null,
            'recurrence_until_date' => '2026-01-25',
            'series_id' => $entry->id,
        ];
        $member = function (string $occKey, string $datetime, string $endDatetime) use ($entry, $bandSpace, $metadata): array {
            $id = 'manual-' . $entry->id . '-' . $occKey;
            return [
                '@id' => '/api/agenda_items/id=' . $id . ';bandSpaceId=' . $bandSpace->id,
                '@type' => 'AgendaItem',
                'id' => $id,
                'band_space_id' => $bandSpace->id,
                'source' => 'manual',
                'source_id' => $entry->id,
                'datetime' => $datetime,
                'end_datetime' => $endDatetime,
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
            'totalItems' => 4,
            'member' => [
                $member('20260104-1800', '2026-01-04T18:00:00+00:00', '2026-01-04T20:00:00+00:00'),
                $member('20260111-1800', '2026-01-11T18:00:00+00:00', '2026-01-11T20:00:00+00:00'),
                $member('20260118-1800', '2026-01-18T18:00:00+00:00', '2026-01-18T20:00:00+00:00'),
                $member('20260125-1800', '2026-01-25T18:00:00+00:00', '2026-01-25T20:00:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda?from=2026-01-01&to=2026-01-31',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_non_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_invalid_date_param_returns_400(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/agenda?from=not-a-date',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
