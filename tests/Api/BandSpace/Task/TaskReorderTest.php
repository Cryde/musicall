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
use App\Validator\BandSpace\TaskReorderPositions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskReorderTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_reorder_same_column_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $c = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 2])->create();

        $aId = (string) $a->id;
        $bId = (string) $b->id;
        $cId = (string) $c->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
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

        $todoId = (string) $todo->id;
        $inProgressId = (string) $inProgress->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $todoId, 'position' => 0],
                    ['id' => $inProgressId, 'position' => 1],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Toutes les tâches doivent avoir le même statut',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Toutes les tâches doivent avoir le même statut',
        ]);

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

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => (string) $task->id, 'position' => 0],
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

        $ownId = (string) $own->id;
        $foreignId = (string) $foreign->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
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

    /**
     * The regression this endpoint exists to stop. A board filtered down to two of the three
     * "À faire" tasks used to send only those two, renumbered 0 and 1, which handed them numbers
     * the third one already held. The column then ordered on its tie-break, for every member of
     * the band space, and a refresh did not repair it.
     */
    public function test_reorder_rejects_a_payload_that_leaves_a_task_of_the_column_out(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $hidden = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 2])->create();

        $aId = (string) $a->id;
        $bId = (string) $b->id;
        $hiddenId = (string) $hidden->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $bId, 'position' => 0],
                    ['id' => $aId, 'position' => 1],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Les positions doivent couvrir exactement les tâches de cette colonne',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Les positions doivent couvrir exactement les tâches de cette colonne',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($aId)->position);
        $this->assertSame(1, $taskRepo->find($bId)->position);
        $this->assertSame(2, $taskRepo->find($hiddenId)->position);
    }

    /**
     * Archived tasks are off the board, so the payload cannot name them and must not have to.
     */
    public function test_reorder_covers_the_column_without_its_archived_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $archived = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
            'position' => 2,
            'archiveDatetime' => new \DateTimeImmutable('-1 day'),
        ])->create();

        $aId = (string) $a->id;
        $bId = (string) $b->id;
        $archivedId = (string) $archived->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $bId, 'position' => 0],
                    ['id' => $aId, 'position' => 1],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(1, $taskRepo->find($aId)->position);
        $this->assertSame(0, $taskRepo->find($bId)->position);
        $this->assertSame(2, $taskRepo->find($archivedId)->position);
    }

    public function test_reorder_rejects_non_contiguous_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();

        $aId = (string) $a->id;
        $bId = (string) $b->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $aId, 'position' => 0],
                    ['id' => $bId, 'position' => 5],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TaskReorderPositions::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'positions',
                    'message' => 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
                    'code' => TaskReorderPositions::ERROR_CODE,
                ],
            ],
            'detail' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
            'type' => '/validation_errors/' . TaskReorderPositions::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($aId)->position);
        $this->assertSame(1, $taskRepo->find($bId)->position);
    }

    public function test_reorder_rejects_a_duplicated_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $a = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $b = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();

        $aId = (string) $a->id;
        $bId = (string) $b->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            [
                'positions' => [
                    ['id' => $aId, 'position' => 0],
                    ['id' => $bId, 'position' => 1],
                    ['id' => $aId, 'position' => 2],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TaskReorderPositions::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'positions',
                    'message' => 'Chaque tâche ne peut apparaître qu\'une seule fois',
                    'code' => TaskReorderPositions::ERROR_CODE,
                ],
            ],
            'detail' => 'positions: Chaque tâche ne peut apparaître qu\'une seule fois',
            'type' => '/validation_errors/' . TaskReorderPositions::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'positions: Chaque tâche ne peut apparaître qu\'une seule fois',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $taskRepo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $taskRepo->find($aId)->position);
        $this->assertSame(1, $taskRepo->find($bId)->position);
    }

    public function test_reorder_rejects_empty_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/reorder',
            ['positions' => []],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
