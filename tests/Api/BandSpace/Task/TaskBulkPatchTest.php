<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\Filter\TaskFilter;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
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
