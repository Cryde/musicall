<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceFileBulkDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_bulk_delete_trashes_every_selected_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $first = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-1.wav'])->create();
        $second = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'live-2.wav'])->create();
        $untouched = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'garde-moi.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $first->id, (string) $second->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNotNull($repo->find($first->id)?->archiveDatetime);
        $this->assertNotNull($repo->find($second->id)?->archiveDatetime);
        $this->assertNull($repo->find($untouched->id)?->archiveDatetime);

        // One Archived activity per file, the way the single file endpoint and the folder cascade write them.
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(2, $activityRepo->findBy(['bandSpace' => $bandSpace->id, 'module' => BandSpaceModule::File]));
    }

    public function test_bulk_delete_by_an_admin_trashes_another_members_file(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member, 'originalName' => 'pas-la-mienne.wav'])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $file->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNotNull($repo->find($file->id)?->archiveDatetime);
    }

    public function test_bulk_delete_revokes_the_share_links_of_every_trashed_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'rough-mix.mp3'])->create();
        $share = BandSpaceFileShareFactory::new(['bandSpaceFile' => $file, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $file->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Trashing a file has to unshare it, or a bandmate restoring it weeks later brings the public URL back.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $shareRepo = self::getContainer()->get(BandSpaceFileShareRepository::class);
        $this->assertNotNull($shareRepo->find($share->id)?->revocationDatetime);
    }

    public function test_bulk_delete_names_every_file_the_member_did_not_create(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $own = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'la-mienne.wav'])->create();
        $firstForeign = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $other, 'originalName' => 'mix-final.wav'])->create();
        $secondForeign = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $other, 'originalName' => 'contrat.pdf'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $own->id, (string) $firstForeign->id, (string) $secondForeign->id]],
            self::HEADERS,
        );

        // The batch is all or nothing, so the answer has to say which rows of the selection are in
        // the way, not just that one of them was.
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $detail = 'Seul le créateur ou un administrateur peut supprimer ces fichiers : mix-final.wav, contrat.pdf';
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

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($own->id)?->archiveDatetime);
        $this->assertNull($repo->find($firstForeign->id)?->archiveDatetime);
    }

    public function test_bulk_delete_names_how_many_files_are_attached_and_to_what(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $free = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'libre.wav'])->create();
        $attachedToTask = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'devis.pdf'])
            ->withAttachment(['type' => 'task', 'id' => \Ramsey\Uuid\Uuid::uuid4()->toString()])
            ->create();
        $attachedToNote = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'paroles.pdf'])
            ->withAttachment(['type' => 'note', 'id' => \Ramsey\Uuid\Uuid::uuid4()->toString()])
            ->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $free->id, (string) $attachedToTask->id, (string) $attachedToNote->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = "2 fichiers sélectionnés sont attachés à une note et une tâche. Détachez-les d'abord depuis les ressources concernées.";
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

        // Not even the unattached file beside them was touched, and nothing was written.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($free->id)?->archiveDatetime);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findBy(['bandSpace' => $bandSpace->id, 'module' => BandSpaceModule::File]));
    }

    public function test_bulk_delete_names_the_single_attached_file_in_the_singular(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $attached = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'facture.pdf'])
            ->withAttachment(['type' => 'finance', 'id' => \Ramsey\Uuid\Uuid::uuid4()->toString()])
            ->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $attached->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = "1 fichier sélectionné est attaché à une entrée financière. Détachez-le d'abord depuis la ressource concernée.";
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
    }

    public function test_bulk_delete_rejects_an_id_from_another_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $own = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'ok.wav'])->create();
        $foreign = BandSpaceFileFactory::new(['bandSpace' => $otherBandSpace, 'createdBy' => $user, 'originalName' => 'ailleurs.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $own->id, (string) $foreign->id]],
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
        $this->assertNull($repo->find($own->id)?->archiveDatetime);
    }

    public function test_bulk_delete_rejects_a_file_already_in_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Already trashed, so it is not a row of the listing the selection came from.
        $trashed = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'deja-corbeille.wav',
            'archiveDatetime' => new \DateTimeImmutable('2026-06-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $trashed->id]],
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

    public function test_bulk_delete_is_forbidden_for_a_non_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner, 'originalName' => 'prive.wav'])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => [(string) $file->id]],
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

    public function test_bulk_delete_requires_at_least_one_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_delete',
            ['file_ids' => []],
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
