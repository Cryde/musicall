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

class AgendaGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $manualId = 'manual-' . $manual->_real()->id;
        $taskId = 'task-' . $task->_real()->id;
        $financeId = 'finance-' . $finance->_real()->id;
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaItem',
            '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/agenda_items/id=' . $manualId . ';bandSpaceId=' . $bandSpace->_real()->id,
                    '@type' => 'AgendaItem',
                    'id' => $manualId,
                    'band_space_id' => $bandSpace->_real()->id,
                    'source' => 'manual',
                    'source_id' => $manual->_real()->id,
                    'datetime' => '2026-06-15T20:00:00+00:00',
                    'end_datetime' => null,
                    'title' => 'Répétition',
                    'description' => 'Préparer le set',
                    'metadata' => ['location' => 'Studio'],
                ],
                [
                    '@id' => '/api/agenda_items/id=' . $taskId . ';bandSpaceId=' . $bandSpace->_real()->id,
                    '@type' => 'AgendaItem',
                    'id' => $taskId,
                    'band_space_id' => $bandSpace->_real()->id,
                    'source' => 'task',
                    'source_id' => $task->_real()->id,
                    'datetime' => '2026-06-20T12:00:00+00:00',
                    'end_datetime' => null,
                    'title' => 'Acheter cordes',
                    'description' => null,
                    'metadata' => [
                        'status' => 'todo',
                        'priority' => 'high',
                        'category_name' => null,
                    ],
                ],
                [
                    '@id' => '/api/agenda_items/id=' . $financeId . ';bandSpaceId=' . $bandSpace->_real()->id,
                    '@type' => 'AgendaItem',
                    'id' => $financeId,
                    'band_space_id' => $bandSpace->_real()->id,
                    'source' => 'finance',
                    'source_id' => $finance->_real()->id,
                    'datetime' => '2026-06-25T00:00:00+00:00',
                    'end_datetime' => null,
                    'title' => 'Location salle',
                    'description' => null,
                    'metadata' => [
                        'type' => $finance->_real()->type->value,
                        'status' => $finance->_real()->status->value,
                        'scope' => $finance->_real()->scope->value,
                        'amount' => $finance->_real()->amount,
                        'amount_min' => null,
                        'amount_max' => null,
                        'category_name' => 'Logistique',
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=2026-06-01&to=2026-06-30',
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=2026-06-01&to=2026-06-30',
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=2026-06-01&to=2026-06-30',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $payload['totalItems']);
        $this->assertSame('manual-' . $inWindow->_real()->id, $payload['member'][0]['id']);
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=2026-06-01&to=2026-06-30',
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda',
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

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda',
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/agenda?from=not-a-date',
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
