<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\TaskRepository;
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
class TaskBulkDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_bulk_delete_by_creator(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task1 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $task1Id = $task1->id;
        $task2Id = $task2->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$task1Id, $task2Id]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNull($repo->find($task1Id));
        $this->assertNull($repo->find($task2Id));
    }

    public function test_bulk_delete_by_admin(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_u', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $creator])->create();
        $taskId = $task->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$taskId]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNull($repo->find($taskId));
    }

    public function test_bulk_delete_detaches_the_files_of_every_deleted_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task1 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $task2 = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $keptTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $file1 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file2 = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $keptFile = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $attachment1 = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file1,
            'sourceType' => 'task',
            'sourceId' => Uuid::fromString((string) $task1->id),
            'attachedBy' => $user,
        ]);
        $attachment2 = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file2,
            'sourceType' => 'task',
            'sourceId' => Uuid::fromString((string) $task2->id),
            'attachedBy' => $user,
        ]);
        $keptAttachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $keptFile,
            'sourceType' => 'task',
            'sourceId' => Uuid::fromString((string) $keptTask->id),
            'attachedBy' => $user,
        ]);

        $attachment1Id = (string) $attachment1->id;
        $attachment2Id = (string) $attachment2->id;
        $keptAttachmentId = (string) $keptAttachment->id;
        $file1Id = (string) $file1->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [(string) $task1->id, (string) $task2->id]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepository->find($attachment1Id));
        $this->assertNull($attachmentRepository->find($attachment2Id));
        $this->assertNotNull($attachmentRepository->find($keptAttachmentId));

        $fileRepository = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNotNull($fileRepository->find($file1Id));
        $this->assertNull($fileRepository->find($file1Id)->archiveDatetime);
    }

    public function test_bulk_delete_rolls_back_when_user_does_not_own_one_task(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'mem_u', 'email' => 'm@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $ownTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        $foreignTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $creator])->create();
        $ownId = $ownTask->id;
        $foreignId = $foreignTask->id;

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$ownId, $foreignId]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNotNull($repo->find($ownId));
        $this->assertNotNull($repo->find($foreignId));
    }

    public function test_bulk_delete_names_every_task_the_member_did_not_create(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'mem_u', 'email' => 'm@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $ownTask = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $member,
            'title' => 'Ma tâche',
        ])->create();
        $firstForeign = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $creator,
            'title' => 'Mix final',
        ])->create();
        $secondForeign = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $creator,
            'title' => 'Réserver le studio',
        ])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$ownTask->id, $firstForeign->id, $secondForeign->id]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        // The batch is all or nothing, so the answer has to say which cards of the selection are in
        // the way, not just that one of them was.
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $detail = 'Seul le créateur ou un administrateur peut supprimer ces tâches : Mix final, Réserver le studio';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 403,
            'type' => '/errors/403',
            'description' => $detail,
        ]);

        self::getContainer()->get('doctrine')->getManager()->clear();
        $repo = self::getContainer()->get(TaskRepository::class);
        $this->assertNotNull($repo->find($ownTask->id));
        $this->assertNotNull($repo->find($firstForeign->id));
    }

    public function test_bulk_delete_rejects_unknown_task_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$task->id, '00000000-0000-0000-0000-000000000000']],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_bulk_delete_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'oth_u', 'email' => 'oth@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => [$task->id]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_bulk_delete_requires_at_least_one_task(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/bulk_delete',
            ['task_ids' => []],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
