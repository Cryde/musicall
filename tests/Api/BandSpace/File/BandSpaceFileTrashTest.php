<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The trash: deleting a file only archives it, so these three endpoints are what make the 30 day grace
 * period usable instead of a slow, silent loss.
 */
#[ResetDatabase]
class BandSpaceFileTrashTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const string CONTENT = "trash test content\n";

    public function test_the_archived_collection_lists_only_the_trash(): void
    {
        [$admin, $bandSpace, $archived] = $this->setupArchivedFile(archivedAt: new \DateTimeImmutable('2026-07-02T09:00:00+00:00'));
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'originalName' => 'live.txt'])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/files?archived=true', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('archived.txt', $response['member'][0]['original_name']);
        $this->assertSame('2026-07-02T09:00:00+00:00', $response['member'][0]['archive_datetime']);
        // Derived from band_space.file_retention_days, which is 30.
        $this->assertSame('2026-08-01T09:00:00+00:00', $response['member'][0]['purge_datetime']);
    }

    public function test_the_default_collection_still_hides_the_trash(): void
    {
        [$admin, $bandSpace] = $this->setupArchivedFile();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'originalName' => 'live.txt'])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/files', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame('live.txt', $response['member'][0]['original_name']);
        $this->assertNull($response['member'][0]['archive_datetime']);
        $this->assertNull($response['member'][0]['purge_datetime']);
    }

    public function test_restoring_clears_the_archive_date_and_records_the_activity(): void
    {
        [$admin, $bandSpace, $file] = $this->setupArchivedFile();
        $fileId = (string) $file->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/files/' . $fileId . '/restore', [], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertNull($response['archive_datetime']);
        $this->assertNull($response['purge_datetime']);

        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)->findOneByIdAndBandSpace($fileId, $bandSpace);
        $this->assertNull($reloaded?->archiveDatetime);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $this->assertSame(BandSpaceFileActivityType::Restored->value, $activities[0]->type);
    }

    /**
     * The quota only counts non-archived files, so a restore puts the whole file back into usage. Without
     * this check a band could sit at its limit and still restore its way past it.
     */
    public function test_restoring_is_refused_when_it_would_exceed_the_quota(): void
    {
        [$admin, $bandSpace, $file] = $this->setupArchivedFile(sizeBytes: 900);
        // A quota with room for the live file but not for the archived one coming back.
        $bandSpace->quotaBytesOverride = 1000;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $live = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'originalName' => 'live.txt'])->create();
        BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $live, 'versionNumber' => 1, 'createdBy' => $admin,
            'mimeType' => 'text/plain', 'size' => 900, 'storagePath' => 'live-' . bin2hex(random_bytes(4)) . '.txt',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Still in the trash.
        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)
            ->findOneByIdAndBandSpace((string) $file->id, $bandSpace);
        $this->assertNotNull($reloaded?->archiveDatetime);
    }

    public function test_restoring_a_file_that_is_not_in_the_trash_conflicts(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $live = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'originalName' => 'live.txt'])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/files/' . $live->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier n'est pas dans la corbeille",
            'status' => 409,
            'type' => '/errors/409',
            'description' => "Ce fichier n'est pas dans la corbeille",
        ]);
    }

    public function test_permanent_delete_removes_the_file_its_versions_and_the_stored_object(): void
    {
        // The test filesystem adapter is `memory`, so it lives in the container. Without disableReboot the
        // kernel would restart between the request and the assertion, handing back an empty filesystem and
        // making "the object is gone" pass whether or not anything was deleted.
        $this->client->disableReboot();

        [$admin, $bandSpace, $file, $storagePath] = $this->setupArchivedFile();
        $fileId = (string) $file->id;
        $objectKey = '/band_space_files/' . $bandSpace->id . '/' . $storagePath;

        /** @var FilesystemOperator $fs */
        $fs = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $sentinelKey = '/band_space_files/' . $bandSpace->id . '/sentinel.txt';
        $fs->write($sentinelKey, 'untouched');
        $this->assertTrue($fs->fileExists($objectKey));

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $fileId . '/permanent', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->findOneByIdAndBandSpace($fileId, $bandSpace));
        // The sentinel proves this filesystem is the one the request wrote through, so the absence of the
        // object below means it was deleted rather than never there.
        $this->assertTrue($fs->fileExists($sentinelKey), 'The in-memory filesystem was reset, the next assertion would be vacuous');
        $this->assertFalse($fs->fileExists($objectKey), 'The stored object must be gone, not just the rows');
        $this->assertSame(0, self::getContainer()->get(BandSpaceFileVersionRepository::class)->count(['bandSpaceFile' => $fileId]));

        // The activity survives the row it points at, so the deletion stays auditable.
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $this->assertSame(BandSpaceFileActivityType::Purged->value, $activities[0]->type);
    }

    public function test_permanent_delete_is_forbidden_for_a_regular_member(): void
    {
        [, $bandSpace, $file] = $this->setupArchivedFile();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/permanent', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez être administrateur pour effectuer cette action',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous devez être administrateur pour effectuer cette action',
        ]);

        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)
            ->findOneByIdAndBandSpace((string) $file->id, $bandSpace));
    }

    public function test_permanent_delete_refuses_a_file_that_is_not_in_the_trash(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $live = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'originalName' => 'live.txt'])->create();

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/files/' . $live->id . '/permanent', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)
            ->findOneByIdAndBandSpace((string) $live->id, $bandSpace));
    }

    public function test_a_non_member_cannot_reach_the_trash(): void
    {
        [, $bandSpace, $file] = $this->setupArchivedFile();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);

        $this->client->loginUser($outsider);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/restore', [], [], self::HEADERS);

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

    /**
     * The trash is frozen while the space itself is pending deletion (M-756): everything is about to go
     * anyway, and both processors go through the guarded checker variants.
     */
    public function test_the_trash_is_frozen_while_the_space_is_pending_deletion(): void
    {
        [$admin, $bandSpace, $file] = $this->setupArchivedFile();
        $bandSpace->deletionScheduledDatetime = new \DateTimeImmutable('+30 days');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/restore', [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }

    /**
     * @return array{0: object, 1: object, 2: BandSpaceFile, 3: string} admin, bandSpace, file, storagePath
     */
    private function setupArchivedFile(?\DateTimeImmutable $archivedAt = null, int $sizeBytes = 19): array
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $admin,
            'originalName' => 'archived.txt',
            'archiveDatetime' => $archivedAt ?? new \DateTimeImmutable('-2 days'),
        ])->create();

        $storagePath = 'trash-' . bin2hex(random_bytes(4)) . '.txt';
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $admin,
            'mimeType' => 'text/plain',
            'size' => $sizeBytes,
            'storagePath' => $storagePath,
        ])->create();

        $file->currentVersion = $version;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        /** @var FilesystemOperator $fs */
        $fs = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $fs->write('/band_space_files/' . $bandSpace->id . '/' . $storagePath, self::CONTENT);

        return [$admin, $bandSpace, $file, $storagePath];
    }
}
