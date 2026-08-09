<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFolderDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_default_strategy_moves_children_to_root(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => '2026', 'parent' => $parent])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $parent])->create();

        $bandSpaceId = $bandSpace->id;
        $parentId = $parent->id;
        $childId = $child->id;
        $fileId = $file->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parentId,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();

        /** @var BandSpaceFolderRepository $folderRepo */
        $folderRepo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $this->assertNull($folderRepo->find($parentId));
        $reloadedChild = $folderRepo->find($childId);
        $this->assertNotNull($reloadedChild);
        $this->assertNull($reloadedChild->parent);

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloadedFile = $fileRepo->find($fileId);
        $this->assertNotNull($reloadedFile);
        $this->assertNull($reloadedFile->folder);
        $this->assertNull($reloadedFile->archiveDatetime);
    }

    public function test_delete_cascade_strategy_admin_archives_subtree_files(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => '2026', 'parent' => $parent])->create();
        $fileInParent = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $parent])->create();
        $fileInChild = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $child])->create();

        $bandSpaceId = $bandSpace->id;
        $parentId = $parent->id;
        $childId = $child->id;
        $fileIds = [$fileInParent->id, $fileInChild->id];

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parentId . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();

        /** @var BandSpaceFolderRepository $folderRepo */
        $folderRepo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $this->assertNull($folderRepo->find($parentId));
        $this->assertNull($folderRepo->find($childId));

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        foreach ($fileIds as $fileId) {
            $reloaded = $fileRepo->find($fileId);
            $this->assertNotNull($reloaded);
            $this->assertNotNull($reloaded->archiveDatetime);
        }
    }

    public function test_delete_cascade_strategy_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member, 'name' => 'Live'])->create();

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul un administrateur peut supprimer un dossier en cascade',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul un administrateur peut supprimer un dossier en cascade',
        ]);
    }

    public function test_delete_move_to_root_by_non_creator_non_admin_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'name' => 'Live'])->create();

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul le créateur ou un administrateur peut supprimer ce dossier',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul le créateur ou un administrateur peut supprimer ce dossier',
        ]);
    }

    /**
     * The cascade used to archive the whole subtree with no attachment check at all, which is exactly
     * what the single file endpoint refuses. Attachment panels list live files only, so the file just
     * vanished from the task or entry holding it and app:band-space:purge destroyed it 30 days later.
     */
    public function test_delete_cascade_refuses_a_subtree_holding_attached_files(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => '2026', 'parent' => $parent])->create();

        $attachedToTask = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $parent])->create();
        $attachedToNote = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $child])->create();
        $free = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $child])->create();

        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedToTask,
            'sourceType' => 'task',
            'sourceId' => Uuid::uuid4(),
            'attachedBy' => $admin,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $attachedToNote,
            'sourceType' => 'note',
            'sourceId' => Uuid::uuid4(),
            'attachedBy' => $admin,
        ]);

        $bandSpaceId = $bandSpace->id;
        $parentId = $parent->id;
        $freeFileId = $free->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parentId . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce dossier contient 2 fichiers attachés à une note et une tâche. Détachez-les d'abord depuis les ressources concernées.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce dossier contient 2 fichiers attachés à une note et une tâche. Détachez-les d'abord depuis les ressources concernées.",
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Nothing at all was written: not the folders, not even the unattached file beside them.
        /** @var BandSpaceFolderRepository $folderRepo */
        $folderRepo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $this->assertNotNull($folderRepo->find($parentId));

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($fileRepo->find($freeFileId)?->archiveDatetime);

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findBy(['bandSpace' => $bandSpaceId, 'module' => BandSpaceModule::File]));
    }

    public function test_delete_cascade_refuses_a_subtree_holding_a_single_attached_file(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $folder])->create();

        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::uuid4(),
            'attachedBy' => $admin,
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce dossier contient 1 fichier attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce dossier contient 1 fichier attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée.",
        ]);
    }

    /**
     * The folder rows are gone and band_space_file.folder_id is ON DELETE SET NULL, so the Archived
     * activity is the only thing left saying where a restored file used to live.
     */
    public function test_delete_cascade_records_an_archived_activity_carrying_the_folder_path(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => '2026', 'parent' => $parent])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $admin,
            'folder' => $child,
            'originalName' => 'soundcheck.wav',
        ])->create();

        $bandSpaceId = $bandSpace->id;
        $fileId = (string) $file->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parent->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::File, $fileId);

        $this->assertCount(1, $activities);
        $this->assertSame(BandSpaceFileActivityType::Archived->value, $activities[0]->type);
        $this->assertSame('soundcheck.wav', $activities[0]->payload['original_name']);
        $this->assertSame('Live / 2026', $activities[0]->payload['folder_path']);
    }

    /**
     * Same rule as the single file endpoint: a link the band watched disappear must not come back to
     * life when somebody restores the file weeks later.
     */
    public function test_delete_cascade_revokes_the_share_links_of_the_archived_files(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $folder])->create();

        $share = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $admin,
            'tokenHash' => hash('sha256', 'cascade-token'),
            'expiryDatetime' => new \DateTimeImmutable('+30 days'),
        ])->create();

        $shareId = $share->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();

        /** @var BandSpaceFileShareRepository $shareRepo */
        $shareRepo = self::getContainer()->get(BandSpaceFileShareRepository::class);
        $this->assertNotNull($shareRepo->find($shareId)?->revocationDatetime);
    }

    /**
     * The cascade archives file by file, so an unbounded subtree would run the whole thing inside one
     * HTTP request. Nothing caps how many files a space holds (the quota caps bytes), so the processor
     * counts first and refuses rather than letting the admin hit a 504 that explains nothing.
     */
    public function test_delete_cascade_refuses_a_subtree_above_the_file_limit(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Photos'])->create();

        $overTheLimit = 2001;
        $this->seedFiles($bandSpace->id, (string) $folder->id, $overTheLimit);

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce dossier contient 2001 fichiers, au-delà de la limite de 2000 par suppression. Supprimez ses sous-dossiers un par un, ou déplacez une partie des fichiers avant de réessayer.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce dossier contient 2001 fichiers, au-delà de la limite de 2000 par suppression. Supprimez ses sous-dossiers un par un, ou déplacez une partie des fichiers avant de réessayer.',
        ]);
    }

    public function test_delete_cascade_accepts_a_subtree_at_the_file_limit(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Photos'])->create();

        // Exactly on the limit: the guard rejects above it, never at it.
        $this->seedFiles($bandSpace->id, (string) $folder->id, 2000);

        $bandSpaceId = $bandSpace->id;
        $folderId = (string) $folder->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $folderId . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertSame(0, $fileRepo->countActiveByFolderIds([$folderId]));
    }

    public function test_delete_move_to_root_by_admin_non_creator_succeeds(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'name' => 'Live'])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    /**
     * Rows straight through the connection: thousands of files is the whole point of the two limit
     * tests, and building them as Foundry objects would cost minutes for state no assertion reads.
     * created_by_id is left null, which the uploader join tolerates and the count ignores.
     */
    private function seedFiles(string $bandSpaceId, string $folderId, int $count): void
    {
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $rows = [];
        $parameters = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = '(?, ?, ?, ?, ?)';
            array_push($parameters, Uuid::uuid4()->toString(), $bandSpaceId, $folderId, 'photo-' . $i . '.jpg', $now);
        }

        $connection->executeStatement(
            'INSERT INTO band_space_file (id, band_space_id, folder_id, original_name, creation_datetime) VALUES '
            . implode(', ', $rows),
            $parameters,
        );
    }
}
