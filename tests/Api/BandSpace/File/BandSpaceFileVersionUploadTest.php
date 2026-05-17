<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileVersionUploadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_upload_version_increments_number_and_updates_current(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'doc.txt',
        ])->create();
        $v1 = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $user,
            'mimeType' => 'text/plain',
            'size' => 30,
        ])->create();

        $file->currentVersion = $v1;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $bandSpaceId = $bandSpace->id;
        $fileId = $file->id;

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/files/' . $fileId . '/versions',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample-v2.txt', 'sample-v2.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getResponseAsArray();
        $this->assertSame(2, $response['version_number']);
        $this->assertTrue($response['is_current']);
        $this->assertSame('text/plain', $response['mime_type']);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloadedFile = $fileRepo->find($fileId);
        $this->assertNotNull($reloadedFile);
        $this->assertNotNull($reloadedFile->currentVersion);
        $this->assertSame(2, $reloadedFile->currentVersion->versionNumber);

        /** @var BandSpaceFileVersionRepository $versionRepo */
        $versionRepo = self::getContainer()->get(BandSpaceFileVersionRepository::class);
        $this->assertCount(2, $versionRepo->findBy(['bandSpaceFile' => $reloadedFile]));

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $versionAdded = array_values(array_filter($activities, fn (\App\Entity\BandSpace\BandSpaceActivity $a): bool => $a->type === 'version_added'));
        $this->assertCount(1, $versionAdded);
        $this->assertSame(2, $versionAdded[0]->payload['version_number']);
        $this->assertSame('text/plain', $versionAdded[0]->payload['mime_type']);
    }

    public function test_upload_version_on_unknown_file_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/00000000-0000-0000-0000-000000000000/versions',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Fichier introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Fichier introuvable',
        ]);
    }

    public function test_upload_version_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($other);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/versions',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample-v2.txt', 'sample-v2.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
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
}
