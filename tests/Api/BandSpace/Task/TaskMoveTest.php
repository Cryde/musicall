<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskStatus;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskMoveTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_move_task_cross_column(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $moved = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $first = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $second = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 1])->create();

        $movedId = (string) $moved->_real()->id;
        $firstId = (string) $first->_real()->id;
        $secondId = (string) $second->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => $movedId,
                'status' => 'in_progress',
                'positions' => [
                    ['id' => $firstId, 'position' => 0],
                    ['id' => $movedId, 'position' => 1],
                    ['id' => $secondId, 'position' => 2],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $movedId,
            'status' => 'in_progress',
            'position' => 1,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(TaskStatus::InProgress, $taskRepo->find($movedId)->status);
        $this->assertSame(1, $taskRepo->find($movedId)->position);
        $this->assertSame(0, $taskRepo->find($firstId)->position);
        $this->assertSame(2, $taskRepo->find($secondId)->position);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Task, $moved->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('status_changed', $activities[0]->type);
        $this->assertSame(['from' => 'todo', 'to' => 'in_progress'], $activities[0]->payload);
    }

    public function test_move_task_same_column_no_status_change(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $taskA = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $taskB = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();

        $aId = (string) $taskA->_real()->id;
        $bId = (string) $taskB->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => $aId,
                'status' => 'todo',
                'positions' => [
                    ['id' => $bId, 'position' => 0],
                    ['id' => $aId, 'position' => 1],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['id' => $aId, 'status' => 'todo', 'position' => 1]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(1, $taskRepo->find($aId)->position);
        $this->assertSame(0, $taskRepo->find($bId)->position);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Task, $taskA->_real()->id));
    }

    public function test_move_task_to_done_sets_completed_datetime(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $taskId = (string) $task->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => $taskId,
                'status' => 'done',
                'positions' => [['id' => $taskId, 'position' => 0]],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertNotNull($taskRepo->find($taskId)->completedDatetime);
    }

    public function test_move_task_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($stranger->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => (string) $task->_real()->id,
                'status' => 'in_progress',
                'positions' => [
                    ['id' => (string) $task->_real()->id, 'position' => 0],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_move_task_invalid_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => (string) $task->_real()->id,
                'status' => 'archived',
                'positions' => [
                    ['id' => (string) $task->_real()->id, 'position' => 0],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_move_task_id_not_in_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $other = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => (string) $task->_real()->id,
                'status' => 'in_progress',
                'positions' => [
                    ['id' => (string) $other->_real()->id, 'position' => 0],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(TaskStatus::Todo, $taskRepo->find((string) $task->_real()->id)->status);
        $this->assertSame(0, $taskRepo->find((string) $task->_real()->id)->position);
    }

    public function test_move_task_atomic_when_position_references_foreign_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $foreignBandSpace = BandSpaceFactory::new()->create();

        $moved = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $sibling = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $foreign = TaskFactory::new(['bandSpace' => $foreignBandSpace, 'status' => TaskStatus::InProgress, 'position' => 5])->create();

        $movedId = (string) $moved->_real()->id;
        $siblingId = (string) $sibling->_real()->id;
        $foreignId = (string) $foreign->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/move',
            [
                'task_id' => $movedId,
                'status' => 'in_progress',
                'positions' => [
                    ['id' => $siblingId, 'position' => 0],
                    ['id' => $movedId, 'position' => 1],
                    ['id' => $foreignId, 'position' => 2],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(TaskStatus::Todo, $taskRepo->find($movedId)->status);
        $this->assertSame(0, $taskRepo->find($movedId)->position);
        $this->assertSame(0, $taskRepo->find($siblingId)->position);
        $this->assertSame(5, $taskRepo->find($foreignId)->position);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Task, $moved->_real()->id));
    }
}
