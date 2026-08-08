<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_task_by_creator(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_task_by_admin(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $creator])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_task_detaches_its_files_and_leaves_them_deletable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Signer le contrat'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $attachment = BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => Uuid::fromString((string) $task->id),
            'attachedBy' => $user,
        ]);
        $taskId = (string) $task->id;
        $fileId = (string) $file->id;
        $attachmentId = (string) $attachment->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $taskId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $attachmentRepository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepository->find($attachmentId));

        $fileRepository = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloadedFile = $fileRepository->find($fileId);
        $this->assertNotNull($reloadedFile, 'The file survives its task: it belongs to the library, not to the task');
        $this->assertNull($reloadedFile->archiveDatetime);

        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $this->assertCount(1, $activities);
        $this->assertSame('source_deleted', $activities[0]->type);
        $this->assertSame([
            'source_type' => 'task',
            'source_id' => $taskId,
            'source_label' => 'Signer le contrat',
        ], $activities[0]->payload);

        // The regression this test exists for. BandSpaceFileDeleteProcessor refuses with a 422 while
        // findByFile returns anything, and that used to be permanent: the detach endpoint 404s once
        // the source is gone, so no call could ever release the file. The endpoint is not exercised
        // here because the stateless api firewall allows one authenticated request per test and it
        // went to the deletion above, so the guard's own query stands in for it.
        $this->assertSame([], $attachmentRepository->findByFile($reloadedFile));
    }

    public function test_delete_task_by_non_creator_non_admin(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $creator])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul le créateur ou un administrateur peut supprimer cette tâche',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul le créateur ou un administrateur peut supprimer cette tâche',
        ]);
    }
}
