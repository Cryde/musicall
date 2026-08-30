<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceFileBulkRestoreTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const string ARCHIVED_AT = '2026-06-01 10:00:00';

    public function test_bulk_restore_brings_every_selected_file_out_of_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $first = $this->archivedFile($bandSpace, $user, 'live-1.wav');
        $second = $this->archivedFile($bandSpace, $user, 'live-2.wav');
        $stays = $this->archivedFile($bandSpace, $user, 'reste-en-corbeille.wav');

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
            ['file_ids' => [(string) $first->id, (string) $second->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($first->id)?->archiveDatetime);
        $this->assertNull($repo->find($second->id)?->archiveDatetime);
        $this->assertNotNull($repo->find($stays->id)?->archiveDatetime);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(2, $activityRepo->findBy(['bandSpace' => $bandSpace->id, 'module' => BandSpaceModule::File]));
    }

    public function test_bulk_restore_by_an_admin_restores_another_members_file(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $file = $this->archivedFile($bandSpace, $member, 'pas-la-mienne.wav');

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
            ['file_ids' => [(string) $file->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($repo->find($file->id)?->archiveDatetime);
    }

    public function test_bulk_restore_refuses_the_whole_batch_when_it_would_exceed_the_quota(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 100])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // 60 each. Restored one at a time both would fit, which is exactly why the quota is asserted
        // for the batch: otherwise a selection walks the space past its limit one file at a time.
        $first = $this->archivedFile($bandSpace, $user, 'gros-1.wav', 60);
        $second = $this->archivedFile($bandSpace, $user, 'gros-2.wav', 60);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
            ['file_ids' => [(string) $first->id, (string) $second->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $detail = 'Quota de stockage dépassé : 0 o utilisés sur 100 o autorisés, il manque 20 o pour ajouter 120 o.';
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
        $this->assertNotNull($repo->find($first->id)?->archiveDatetime);
        $this->assertNotNull($repo->find($second->id)?->archiveDatetime);

        // The quota is asserted before the transaction opens, so not even an activity was written.
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findBy(['bandSpace' => $bandSpace->id, 'module' => BandSpaceModule::File]));
    }

    public function test_bulk_restore_names_every_file_that_is_not_in_the_trash(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $archived = $this->archivedFile($bandSpace, $user, 'corbeille.wav');
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'vivant-1.wav'])->create();
        $firstLive = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'vivant-2.wav'])->create();
        $secondLive = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'vivant-3.wav'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
            ['file_ids' => [(string) $archived->id, (string) $firstLive->id, (string) $secondLive->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $detail = 'Ces fichiers ne sont pas dans la corbeille : vivant-2.wav, vivant-3.wav';
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => 409,
            'type' => '/errors/409',
            'description' => $detail,
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNotNull($repo->find($archived->id)?->archiveDatetime);
    }

    public function test_bulk_restore_names_every_file_the_member_did_not_create(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();

        $own = $this->archivedFile($bandSpace, $user, 'la-mienne.wav');
        $firstForeign = $this->archivedFile($bandSpace, $other, 'mix-final.wav');
        $secondForeign = $this->archivedFile($bandSpace, $other, 'contrat.pdf');

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
            ['file_ids' => [(string) $own->id, (string) $firstForeign->id, (string) $secondForeign->id]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $detail = 'Seul le créateur ou un administrateur peut restaurer ces fichiers : mix-final.wav, contrat.pdf';
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
        $this->assertNotNull($repo->find($own->id)?->archiveDatetime);
    }

    public function test_bulk_restore_rejects_an_id_from_another_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $otherBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $own = $this->archivedFile($bandSpace, $user, 'ok.wav');
        $foreign = $this->archivedFile($otherBandSpace, $user, 'ailleurs.wav');

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
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
    }

    public function test_bulk_restore_is_forbidden_for_a_non_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $file = $this->archivedFile($bandSpace, $owner, 'prive.wav');

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
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

    public function test_bulk_restore_requires_at_least_one_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/bulk_restore',
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

    /**
     * A trashed file with one version, so the batch has bytes to weigh against the quota.
     */
    private function archivedFile(object $bandSpace, object $createdBy, string $originalName, int $size = 10): object
    {
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $createdBy,
            'originalName' => $originalName,
            'archiveDatetime' => new \DateTimeImmutable(self::ARCHIVED_AT),
        ])->create();

        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $createdBy,
            'size' => $size,
        ])->create();

        $file->currentVersion = $version;
        \Zenstruck\Foundry\Persistence\save($file);

        return $file;
    }
}
