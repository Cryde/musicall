<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskStatus;
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

class TaskReorderTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_reorder_same_column_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $c = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 2])->create();

        $aId = (string) $a->_real()->id;
        $bId = (string) $b->_real()->id;
        $cId = (string) $c->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $cId, 'position' => 0],
                    ['id' => $aId, 'position' => 1],
                    ['id' => $bId, 'position' => 2],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($cId)->position);
        $this->assertSame(1, $taskRepo->find($aId)->position);
        $this->assertSame(2, $taskRepo->find($bId)->position);
    }

    public function test_reorder_rejects_mixed_statuses(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $todo = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $inProgress = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();

        $todoId = (string) $todo->_real()->id;
        $inProgressId = (string) $inProgress->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $todoId, 'position' => 5],
                    ['id' => $inProgressId, 'position' => 6],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($todoId)->position);
        $this->assertSame(0, $taskRepo->find($inProgressId)->position);
    }

    public function test_reorder_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($stranger->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => (string) $task->_real()->id, 'position' => 0],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_reorder_rejects_foreign_band_space_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $foreignBandSpace = BandSpaceFactory::new()->create();

        $own = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $foreign = TaskFactory::new(['bandSpace' => $foreignBandSpace, 'status' => TaskStatus::Todo, 'position' => 5])->create();

        $ownId = (string) $own->_real()->id;
        $foreignId = (string) $foreign->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $ownId, 'position' => 0],
                    ['id' => $foreignId, 'position' => 1],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($ownId)->position);
        $this->assertSame(5, $taskRepo->find($foreignId)->position);
    }

    public function test_reorder_rejects_empty_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/reorder',
            ['positions' => []],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
