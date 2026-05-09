<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFileDownloadTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const string V1_CONTENT = "Hello, MusicAll Files module!\n";
    private const string V2_CONTENT = "Version 2 content — totally different.\n";

    public function test_download_current_version_returns_v2_bytes(): void
    {
        $this->client->disableReboot();
        [$user, $bandSpace, $file, , ] = $this->setupFileWithTwoVersions(currentVersionNumber: 2);

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
        );

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/plain', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename=doc.txt',
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );
        $this->assertSame(self::V2_CONTENT, $this->getStreamedBody());
    }

    public function test_download_specific_old_version_returns_v1_bytes(): void
    {
        $this->client->disableReboot();
        [$user, $bandSpace, $file, , ] = $this->setupFileWithTwoVersions(currentVersionNumber: 2);

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/versions/1/download',
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::V1_CONTENT, $this->getStreamedBody());
    }

    public function test_download_unknown_version_returns_404(): void
    {
        $this->client->disableReboot();
        [$user, $bandSpace, $file] = $this->setupFileWithTwoVersions(currentVersionNumber: 1);

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/versions/99/download',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Version 99 introuvable pour ce fichier',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Version 99 introuvable pour ce fichier',
        ]);
    }

    public function test_download_not_member_returns_403(): void
    {
        $this->client->disableReboot();
        [, $bandSpace, $file] = $this->setupFileWithTwoVersions(currentVersionNumber: 1);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);

        $this->client->loginUser($other);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
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

    /**
     * @return array{0: object, 1: object, 2: object, 3: object, 4: object}  user, bandSpace, file, v1, v2 (proxies)
     */
    private function setupFileWithTwoVersions(int $currentVersionNumber): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'doc.txt',
        ])->create();

        $v1StoragePath = 'test-v1-' . bin2hex(random_bytes(4)) . '.txt';
        $v2StoragePath = 'test-v2-' . bin2hex(random_bytes(4)) . '.txt';

        $v1 = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $user,
            'mimeType' => 'text/plain',
            'size' => strlen(self::V1_CONTENT),
            'storagePath' => $v1StoragePath,
        ])->create();
        $v2 = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 2,
            'createdBy' => $user,
            'mimeType' => 'text/plain',
            'size' => strlen(self::V2_CONTENT),
            'storagePath' => $v2StoragePath,
        ])->create();

        $file->currentVersion = $currentVersionNumber === 2 ? $v2 : $v1;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        /** @var FilesystemOperator $fs */
        $fs = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $bandSpaceId = $bandSpace->id;
        $fs->write('/band_space_files/' . $bandSpaceId . '/' . $v1StoragePath, self::V1_CONTENT);
        $fs->write('/band_space_files/' . $bandSpaceId . '/' . $v2StoragePath, self::V2_CONTENT);

        return [$user, $bandSpace, $file, $v1, $v2];
    }

    private function getStreamedBody(): string
    {
        return (string) $this->client->getInternalResponse()->getContent();
    }
}
