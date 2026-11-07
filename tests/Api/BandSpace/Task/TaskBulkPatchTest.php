<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\Filter\TaskFilter;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskBulkPatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_bulk_archive_marks_done_tasks_archived(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task1 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task1->id, $task2->id],
                'archived' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertNotNull($task1->archiveDatetime);
        $this->assertNotNull($task2->archiveDatetime);
    }

    public function test_bulk_archive_rolls_back_when_a_task_is_not_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $done = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();
        $todo = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$done->id, $todo->id],
                'archived' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $reloadedDone = $repo->find($done->id);
        $this->assertNull($reloadedDone->archiveDatetime);
    }

    public function test_bulk_archive_names_every_task_that_is_not_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $done = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mix final',
            'status' => TaskStatus::Done,
        ])->create();
        $todo = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Réserver le studio',
            'status' => TaskStatus::Todo,
        ])->create();
        $inProgress = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Écrire le pont',
            'status' => TaskStatus::InProgress,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$done->id, $todo->id, $inProgress->id],
                'archived' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        // The batch is all or nothing, so the answer has to say which cards of the selection are in
        // the way, not just that one of them was.
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = 'Seules les tâches terminées peuvent être archivées : Réserver le studio, Écrire le pont';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 422,
            'type' => '/errors/422',
            'description' => $detail,
        ]);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNull($repo->find($done->id)->archiveDatetime);
    }

    public function test_bulk_patch_drops_a_position_sent_in_the_payload(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
            'category' => null,
            'position' => 2,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task->id],
                'category_id' => $category->id,
                'position' => 0,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        // This endpoint keeps a whitelist of the fields it forwards, so a position never reaches the
        // update procedure and never gets the chance to be refused. The batch goes through.
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $refreshed = self::getContainer()->get(TaskRepository::class)->find($task->id);
        $this->assertSame((string) $category->id, (string) $refreshed->category->id);
        $this->assertSame(2, $refreshed->position);
    }

    public function test_bulk_set_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $task1 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => null])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => null])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task1->id, $task2->id],
                'category_id' => $category->id,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame((string) $category->id, (string) $repo->find($task1->id)->category->id);
        $this->assertSame((string) $category->id, (string) $repo->find($task2->id)->category->id);
    }

    public function test_bulk_clear_category_with_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'category' => $category])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task->id],
                'category_id' => null,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNull($repo->find($task->id)->category);
    }

    public function test_bulk_replace_assignees(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $assigneeA = UserFactory::new()->create(['username' => 'a_user', 'email' => 'a@test.com']);
        $assigneeB = UserFactory::new()->create(['username' => 'b_user', 'email' => 'b@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assigneeA])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assigneeB])->create();

        $task1 = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'assignees' => new ArrayCollection([$assigneeA]),
        ])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task1->id, $task2->id],
                'assignee_ids' => [$assigneeB->id],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        foreach ([$task1, $task2] as $proxy) {
            $reloaded = $repo->find($proxy->id);
            $ids = array_map(fn($u): string => (string) $u->id, $reloaded->assignees->toArray());
            $this->assertSame([(string) $assigneeB->id], $ids);
        }
    }

    public function test_bulk_status_moves_every_task_to_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $todo = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $inProgress = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$todo->id, $inProgress->id], 'status' => 'done'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        foreach ([[$todo->id, 'todo'], [$inProgress->id, 'in_progress']] as [$id, $from]) {
            $reloaded = $repo->find($id);
            $this->assertSame(TaskStatus::Done, $reloaded->status);
            $this->assertNotNull($reloaded->completedDatetime);

            $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $id);
            $this->assertCount(1, $activities);
            $this->assertSame('status_changed', $activities[0]->type);
            $this->assertSame(['from' => $from, 'to' => 'done'], $activities[0]->payload);
        }

        $signals = array_values(array_filter(
            $hub->updates,
            static fn(Update $update): bool => str_contains($update->getData(), '"band_space_changed"'),
        ));
        $this->assertCount(1, $signals);
        $this->assertSame(
            json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $bandSpace->id, 'module' => 'task'], JSON_THROW_ON_ERROR),
            $signals[0]->getData(),
        );
    }

    public function test_bulk_status_clears_the_completion_date_of_tasks_leaving_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $first = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'position' => 0,
            'completedDatetime' => new \DateTimeImmutable('2026-10-01 10:00:00'),
        ])->create();
        $second = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'position' => 1,
            'completedDatetime' => new \DateTimeImmutable('2026-10-02 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$first->id, $second->id], 'status' => 'todo'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        foreach ([$first->id, $second->id] as $id) {
            $reloaded = $repo->find($id);
            $this->assertSame(TaskStatus::Todo, $reloaded->status);
            $this->assertNull($reloaded->completedDatetime);

            $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $id);
            $this->assertCount(1, $activities);
            $this->assertSame(['from' => 'done', 'to' => 'todo'], $activities[0]->payload);
        }
    }

    /**
     * Ticked in a scrambled order from two columns: they land in the order the board shows them,
     * the first column before the last, top to bottom within each.
     */
    public function test_bulk_status_appends_the_moved_tasks_to_the_target_column_in_board_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $first = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $second = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 1])->create();
        $third = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 2])->create();
        $todoHigh = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1])->create();
        $todoLow = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 4])->create();
        $done = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done, 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$done->id, $todoLow->id, $todoHigh->id], 'status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $bandSpaceEntity = self::getContainer()->get('doctrine')->getRepository($bandSpace::class)->find($bandSpace->id);
        $column = self::getContainer()->get(TaskRepository::class)->findActiveColumn($bandSpaceEntity, TaskStatus::InProgress);
        $positions = [];
        foreach ($column as $task) {
            $positions[(string) $task->id] = $task->position;
        }
        asort($positions);

        $this->assertSame([
            (string) $first->id => 0,
            (string) $second->id => 1,
            (string) $third->id => 2,
            (string) $todoHigh->id => 3,
            (string) $todoLow->id => 4,
            (string) $done->id => 5,
        ], $positions);
    }

    /**
     * Terminé shows its cards newest completion first, whatever their stored positions, so that is
     * the order they keep when they leave it.
     */
    public function test_bulk_status_moves_done_tasks_in_the_order_the_done_column_shows_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $todo = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();
        $completedFirst = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'position' => 0,
            'completedDatetime' => new \DateTimeImmutable('2026-10-01 10:00:00'),
        ])->create();
        $completedLast = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'position' => 1,
            'completedDatetime' => new \DateTimeImmutable('2026-10-05 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$completedFirst->id, $completedLast->id], 'status' => 'todo'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $repo->find($todo->id)->position);
        $this->assertSame(1, $repo->find($completedLast->id)->position);
        $this->assertSame(2, $repo->find($completedFirst->id)->position);
    }

    public function test_bulk_status_leaves_a_task_already_in_the_target_column_where_it_is(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $alreadyThere = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 0])->create();
        $neighbour = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::InProgress, 'position' => 1])->create();
        $moved = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$alreadyThere->id, $moved->id], 'status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertSame(0, $repo->find($alreadyThere->id)->position);
        $this->assertSame(1, $repo->find($neighbour->id)->position);
        $this->assertSame(2, $repo->find($moved->id)->position);
        $this->assertSame(TaskStatus::InProgress, $repo->find($moved->id)->status);

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $alreadyThere->id));
        $this->assertCount(1, $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $moved->id));
    }

    public function test_bulk_status_and_category_are_both_applied(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $task1 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0, 'category' => null])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 1, 'category' => null])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$task1->id, $task2->id], 'status' => 'in_progress', 'category_id' => $category->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        foreach ([[$task1->id, 0], [$task2->id, 1]] as [$id, $position]) {
            $reloaded = $repo->find($id);
            $this->assertSame(TaskStatus::InProgress, $reloaded->status);
            $this->assertSame($position, $reloaded->position);
            $this->assertSame((string) $category->id, (string) $reloaded->category->id);
        }
    }

    public function test_bulk_status_refuses_an_unknown_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$task->id], 'status' => 'blocked'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'Le statut doit être "todo", "in_progress" ou "done"',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'status: Le statut doit être "todo", "in_progress" ou "done"',
            'description' => 'status: Le statut doit être "todo", "in_progress" ou "done"',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
        ]);
    }

    /**
     * A status cannot be cleared, so an explicit null is a mistake, not "leave it as it is".
     */
    public function test_bulk_status_refuses_a_null_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$task->id], 'status' => null],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'Cette valeur doit être de type string.',
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => 'status: Cette valeur doit être de type string.',
            'description' => 'status: Cette valeur doit être de type string.',
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
        ]);
    }

    /**
     * The tasks are written in the order of task_ids and each one is flushed, so the archived task
     * sent last is only refused once the two before it are written: the transaction has to undo them.
     */
    public function test_bulk_status_refuses_a_selection_holding_an_archived_task_and_writes_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $active = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 3])->create();
        $otherActive = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Todo, 'position' => 4])->create();
        $archived = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'position' => 2,
            'archiveDatetime' => new \DateTimeImmutable('2026-10-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$active->id, $otherActive->id, $archived->id], 'status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Une tâche archivée est en lecture seule, désarchive-la pour la modifier',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Une tâche archivée est en lecture seule, désarchive-la pour la modifier',
        ]);

        self::getContainer()->get('doctrine')->resetManager();
        $repo = self::getContainer()->get(TaskRepository::class);
        foreach ([[$active->id, 3], [$otherActive->id, 4]] as [$id, $position]) {
            $reloaded = $repo->find($id);
            $this->assertSame(TaskStatus::Todo, $reloaded->status);
            $this->assertSame($position, $reloaded->position);
        }
        $reloadedArchived = $repo->find($archived->id);
        $this->assertSame(TaskStatus::Done, $reloadedArchived->status);
        $this->assertSame(2, $reloadedArchived->position);

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        foreach ([$active->id, $otherActive->id, $archived->id] as $id) {
            $this->assertCount(0, $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $id));
        }
    }

    public function test_bulk_patch_rejects_unknown_task_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            [
                'task_ids' => [$task->id, '00000000-0000-0000-0000-000000000000'],
                'archived' => true,
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_bulk_patch_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_u', 'email' => 'o@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$task->id], 'archived' => true],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_bulk_patch_requires_at_least_one_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [], 'archived' => true],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_bulk_patch_does_not_touch_tasks_outside_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpaceA = BandSpaceFactory::new()->create();
        $bandSpaceB = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpaceA, 'user' => $user])->create();
        $taskInB = TaskFactory::new(['bandSpace' => $bandSpaceB, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceA->id . '/tasks/bulk_patch',
            ['task_ids' => [$taskInB->id], 'archived' => true],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNull($repo->find($taskInB->id)->archiveDatetime);
    }

    public function test_archived_filter_after_bulk_archive(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'status' => TaskStatus::Done])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_patch',
            ['task_ids' => [$task->id], 'archived' => true],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $bandSpaceEntity = self::getContainer()->get('doctrine')->getRepository($bandSpace::class)->find($bandSpace->id);

        $active = $repo->findByBandSpace($bandSpaceEntity, new TaskFilter(archived: false));
        $archived = $repo->findByBandSpace($bandSpaceEntity, new TaskFilter(archived: true));
        $this->assertCount(0, $active);
        $this->assertCount(1, $archived);
    }
}
