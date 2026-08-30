<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceFileBulkPatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_bulk_patch_moves_every_selected_file_into_the_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $target = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $first = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-1.wav'])->create();
        $second = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-2.wav'])->create();
        $untouched = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'reste.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $first->id, (string) $second->id], 'folder_id' => (string) $target->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertSame((string) $target->id, (string) $repo->find($first->id)?->folder?->id);
        $this->assertSame((string) $target->id, (string) $repo->find($second->id)?->folder?->id);
        $this->assertNull($repo->find($untouched->id)?->folder);

        // Stamped because they moved, and the file left out of the selection is untouched.
        $this->assertNotNull($repo->find($first->id)?->updateDatetime);
        $this->assertNotNull($repo->find($second->id)?->updateDatetime);
        $this->assertNull($repo->find($untouched->id)?->updateDatetime);
    }

    public function test_bulk_patch_moves_files_back_to_the_root_with_a_null_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'live-1.wav',
            'folder' => $folder,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $file->id], 'folder_id' => null],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($file->id)?->folder);
    }

    public function test_bulk_patch_does_not_stamp_a_file_that_did_not_move(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $alreadyThere = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'deja-dedans.wav',
            'folder' => $folder,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $alreadyThere->id], 'folder_id' => (string) $folder->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Moving a file to the folder it is already in is a no-op, so nothing is stamped and no
        // Moved activity is written. The single file PATCH draws the same distinction.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertSame((string) $folder->id, (string) $repo->find($alreadyThere->id)?->folder?->id);
        $this->assertNull($repo->find($alreadyThere->id)?->updateDatetime);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findBy(['bandSpace' => $bandSpace->id, 'module' => BandSpaceModule::File]));
    }

    public function test_bulk_patch_moves_a_file_created_by_another_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $target = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $other, 'originalName' => 'pas-la-mienne.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $file->id], 'folder_id' => (string) $target->id],
            self::HEADERS,
        );

        // Moving is member level, like the single file PATCH. Only deleting is creator-or-admin.
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertSame((string) $target->id, (string) $repo->find($file->id)?->folder?->id);
    }

    public function test_bulk_patch_drops_a_field_outside_the_allowlist(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $target = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-1.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            [
                'file_ids' => [(string) $file->id],
                'folder_id' => (string) $target->id,
                // Renaming a whole selection to one name is never what anybody meant, so the
                // allowlist drops it rather than applying it twelve times.
                'original_name' => 'renomme-en-lot.wav',
            ],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloaded = $repo->find($file->id);
        $this->assertSame('live-1.wav', $reloaded?->originalName);
        $this->assertSame((string) $target->id, (string) $reloaded?->folder?->id);
    }

    public function test_bulk_patch_rejects_a_folder_from_another_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $foreignFolder = BandSpaceFolderFactory::new(['bandSpace' => $otherBandSpace, 'createdBy' => $user, 'name' => 'Ailleurs'])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-1.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $file->id], 'folder_id' => (string) $foreignFolder->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = 'Dossier introuvable dans ce Band Space';
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($file->id)?->folder);
    }

    public function test_bulk_patch_rejects_an_id_from_another_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $target = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        $own = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'ok.wav'])->create();
        $foreign = BandSpaceFileFactory::new(['bandSpace' => $otherBandSpace, 'createdBy' => $user, 'originalName' => 'ailleurs.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $own->id, (string) $foreign->id], 'folder_id' => (string) $target->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $detail = 'Fichier ' . $foreign->id . ' introuvable dans ce Band Space';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 400,
            'type' => '/errors/400',
            'description' => $detail,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($own->id)?->folder);
    }

    public function test_bulk_patch_rejects_a_file_already_in_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $target = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Concerts'])->create();
        // Not a row of the listing the selection came from, so the same refusal as bulk delete.
        $trashed = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'deja-corbeille.wav',
            'archiveDatetime' => new \DateTimeImmutable('2026-06-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $trashed->id], 'folder_id' => (string) $target->id],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $detail = 'Fichier ' . $trashed->id . ' introuvable dans ce Band Space';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 400,
            'type' => '/errors/400',
            'description' => $detail,
        ]);
    }

    public function test_bulk_patch_is_forbidden_for_a_non_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'originalName' => 'prive.wav'])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [(string) $file->id], 'folder_id' => null],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    public function test_bulk_patch_requires_at_least_one_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_patch',
            ['file_ids' => [], 'folder_id' => null],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'file_ids',
                    'message' => 'Au moins un fichier doit être sélectionné',
                    'code' => 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
                ],
            ],
            'detail' => 'file_ids: Au moins un fichier doit être sélectionné',
            'type' => '/validation_errors/bef8e338-6ae5-4caf-b8e2-50e7b0579e69',
            'title' => 'An error occurred',
            'description' => 'file_ids: Au moins un fichier doit être sélectionné',
        ]);
    }
}
