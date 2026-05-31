<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskStatus;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_task_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => $task->title,
            'description' => null,
            'status' => 'in_progress',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 0,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('status_changed', $activities[0]->type);
        $this->assertSame(['from' => 'todo', 'to' => 'in_progress'], $activities[0]->payload);
    }

    public function test_update_task_partial(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Original title',
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['title' => 'Updated title'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'Updated title',
            'description' => null,
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 0,
        ]);
    }

    public function test_update_response_preserves_file_count(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => Uuid::fromString($task->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['description' => 'Une description'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => $task->title,
            'description' => 'Une description',
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 1,
        ]);
    }

    public function test_completed_datetime_set_when_moving_to_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['status' => 'done'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertNotNull($refreshed->completedDatetime);
    }

    public function test_completed_datetime_cleared_when_leaving_done(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Done,
            'completedDatetime' => new \DateTimeImmutable('2026-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertNull($refreshed->completedDatetime);
    }

    public function test_archive_done_task_records_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Mix final',
            'status' => TaskStatus::Done,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['archived' => true],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertNotNull($refreshed->archiveDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'Mix final',
            'description' => null,
            'status' => 'done',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => $refreshed->archiveDatetime->format(\DateTimeInterface::ATOM),
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 0,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('task_archived', $activities[0]->type);
    }

    public function test_archive_rejects_non_done_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['archived' => true],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertNull($refreshed->archiveDatetime);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id));
    }

    public function test_unarchive_records_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Master cassette',
            'status' => TaskStatus::Done,
            'archiveDatetime' => new \DateTimeImmutable('2026-04-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['archived' => false],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(\App\Repository\BandSpace\TaskRepository::class);
        $refreshed = $repo->find($task->id);
        $this->assertNull($refreshed->archiveDatetime);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'title' => 'Master cassette',
            'description' => null,
            'status' => 'done',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
            'comment_count' => 0,
            'file_count' => 0,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('task_unarchived', $activities[0]->type);
    }

    public function test_update_task_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_adding_an_assignee_on_update_notifies_the_new_assignee(): void
    {
        $actor = UserFactory::new()->asBaseUser()->create();
        $assignee = UserFactory::new()->create(['username' => 'late_assignee', 'email' => 'late@test.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $actor])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assignee])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $actor, 'title' => 'Tâche à assigner'])->create();

        $this->client->loginUser($actor);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            ['assignee_ids' => [$assignee->id]],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($assignee, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceTaskAssignment, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => (string) $bandSpace->id,
            'task_id' => (string) $task->id,
            'task_title' => 'Tâche à assigner',
            'actor_id' => (string) $actor->id,
            'actor_username' => $actor->username,
        ], $notifications[0]->payload);

        $this->assertCount(0, $notificationRepository->findForRecipient($actor, 10, 0));
    }
}
