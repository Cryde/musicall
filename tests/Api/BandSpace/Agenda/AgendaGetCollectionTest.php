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
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
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
                    'metadata' => ['location' => 'Studio'],
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
        ])->create();
        $task->assignees->add($assignee);
        self::getContainer()->get('doctrine')->getManager()->flush();

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
        $this->assertJsonContains(['totalItems' => 0]);
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
        $payload = json_decode($this->client->getResponse()->getContent(), true);
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
        $payload = json_decode($this->client->getResponse()->getContent(), true);
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
        $this->assertJsonContains(['totalItems' => 0]);
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
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $payload['totalItems']);
        $this->assertSame('Demain', $payload['member'][0]['title']);
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
