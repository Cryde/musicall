<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceTaskFileDetachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_detach_clears_attachment_keeps_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $fileRepo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepo->findOneByFileAndSource($reloaded, 'task', $task->id));
    }

    public function test_detach_with_archive_query_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->id);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->archiveDatetime);
    }

    /**
     * Detaching and trashing in one call is still trashing, so the file's public links die with it as
     * they do from the trash button.
     */
    public function test_detach_with_archive_revokes_the_share_links_of_the_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);
        $share = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $user,
            'expiryDatetime' => new \DateTimeImmutable('+30 days'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $fileId = (string) $file->id;
        $shareId = $share->id;

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $task->id . '/files/' . $fileId . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(BandSpaceFileShareRepository::class)->find($shareId)?->revocationDatetime);

        $reloadedBandSpace = self::getContainer()->get(BandSpaceRepository::class)->find($bandSpaceId);
        $types = array_map(
            static fn ($activity): string => $activity->type,
            self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($reloadedBandSpace, BandSpaceModule::File, $fileId),
        );
        sort($types);
        $this->assertSame(
            [
                BandSpaceFileActivityType::Archived->value,
                BandSpaceFileActivityType::Detached->value,
                BandSpaceFileActivityType::ShareRevoked->value,
            ],
            $types,
        );
    }

    /**
     * The trash button refuses a file another source still points at, since trashing it would take it
     * off that note too. Trashing on the way off a task must not be the way around that.
     */
    public function test_detach_with_archive_while_attached_to_a_note_returns_422_and_detaches_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => (string) $note->id,
            'attachedBy' => $user,
        ]);

        $fileId = (string) $file->id;
        $taskId = (string) $task->id;

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $taskId . '/files/' . $fileId . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier est attaché à une note. Détache-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce fichier est attaché à une note. Détache-le d'abord depuis la ressource concernée.",
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);
        $this->assertNotNull(
            self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findOneByFileAndSource($reloaded, 'task', $taskId),
        );
    }

    public function test_detach_with_archive_while_attached_to_a_note_names_the_note_in_english(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => (string) $note->id,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id . '?archive=true',
            server: ['HTTP_X_LOCALE' => 'en'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'This file is attached to a note. Detach it from that resource first.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'This file is attached to a note. Detach it from that resource first.',
        ]);
    }

    /** The task being detached from is the only other one: the file is attached to two tasks. */
    public function test_detach_with_archive_while_attached_to_another_task_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $otherTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        foreach ([$task, $otherTask] as $source) {
            BandSpaceFileAttachmentFactory::createOne([
                'bandSpaceFile' => $file,
                'sourceType' => 'task',
                'sourceId' => (string) $source->id,
                'attachedBy' => $user,
            ]);
        }

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier est attaché à une tâche. Détache-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce fichier est attaché à une tâche. Détache-le d'abord depuis la ressource concernée.",
        ]);
    }

    /** A chat image never blocks the trash (#973), here no more than from Files. */
    public function test_detach_with_archive_trashes_a_file_also_posted_in_the_chat(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => BandSpaceFileSourceTypes::CHAT_MESSAGE,
            'sourceId' => Uuid::v7()->toRfc4122(),
            'attachedBy' => $user,
        ]);

        $fileId = (string) $file->id;

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $fileId . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId)?->archiveDatetime);
    }

    public function test_detach_with_archive_by_a_member_who_did_not_upload_the_file_returns_403(): void
    {
        $uploader = UserFactory::new()->asBaseUser()->create(['username' => 'uploader', 'email' => 'uploader@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $uploader])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $uploader,
        ]);

        $fileId = (string) $file->id;
        $taskId = (string) $task->id;

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $taskId . '/files/' . $fileId . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul le créateur ou un administrateur peut supprimer ce fichier',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul le créateur ou un administrateur peut supprimer ce fichier',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);
        $this->assertNotNull(
            self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findOneByFileAndSource($reloaded, 'task', $taskId),
        );
    }

    /** Only the trash is the uploader's or an admin's: any member may still take the file off the task. */
    public function test_detach_without_archive_by_a_member_who_did_not_upload_the_file_succeeds(): void
    {
        $uploader = UserFactory::new()->asBaseUser()->create(['username' => 'uploader', 'email' => 'uploader@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $uploader])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $uploader,
        ]);

        $fileId = (string) $file->id;

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $fileId,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId)?->archiveDatetime);
    }

    public function test_detach_with_archive_by_an_admin_trashes_another_members_file(): void
    {
        $uploader = UserFactory::new()->asBaseUser()->create(['username' => 'uploader', 'email' => 'uploader@test.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $uploader])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $uploader])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $uploader,
        ]);

        $fileId = (string) $file->id;

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $fileId . '?archive=true',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId)?->archiveDatetime);
    }

    public function test_detach_file_not_attached_to_task_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $otherTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $otherTask->id,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le fichier n'est pas attaché à cette tâche",
            'status' => 404,
            'type' => '/errors/404',
            'description' => "Le fichier n'est pas attaché à cette tâche",
        ]);
    }

    public function test_detach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $member,
        ]);

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/files/' . $file->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Tu n'es pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Tu n'es pas membre de ce Band Space",
        ]);
    }
}
