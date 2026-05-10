<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceFilePublicShareDownloadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string FILE_CONTENT = "Public-share download payload\n";

    public function test_public_download_streams_correct_bytes(): void
    {
        $this->client->disableReboot();
        ['bandSpace' => $bandSpace, 'file' => $file, 'token' => $token] = $this->setupShare();

        $this->client->request('GET', '/api/shares/' . $token . '/download');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/plain', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename=doc.txt',
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );
        $this->assertSame(self::FILE_CONTENT, $this->client->getInternalResponse()->getContent());
    }

    public function test_public_download_increments_access_count_and_records_activity(): void
    {
        $this->client->disableReboot();
        ['bandSpace' => $bandSpace, 'file' => $file, 'token' => $token, 'shareId' => $shareId, 'fileId' => $fileId] = $this->setupShare();

        $this->client->request('GET', '/api/shares/' . $token . '/download');
        $this->assertResponseIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        /** @var BandSpaceFileShareRepository $repo */
        $repo = self::getContainer()->get(BandSpaceFileShareRepository::class);
        $reloaded = $repo->find($shareId);
        $this->assertNotNull($reloaded);
        $this->assertSame(1, $reloaded->accessCount);
        $this->assertNotNull($reloaded->lastAccessDatetime);

        /** @var BandSpaceActivityRepository $activityRepo */
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::File, $fileId);
        $accessed = array_values(array_filter($activities, fn (\App\Entity\BandSpace\BandSpaceActivity $a): bool => $a->type === 'public_accessed'));
        $this->assertCount(1, $accessed);
        $this->assertNull($accessed[0]->actor);
        $this->assertSame($shareId, $accessed[0]->payload['share_id']);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->client->request('GET', '/api/shares/totally-fake-token/download');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Lien de partage introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Lien de partage introuvable',
        ]);
    }

    public function test_revoked_share_returns_410(): void
    {
        ['token' => $token] = $this->setupShare(revoke: true);

        $this->client->request('GET', '/api/shares/' . $token . '/download');

        $this->assertResponseStatusCodeSame(Response::HTTP_GONE);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/410',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce lien de partage a été révoqué',
            'status' => 410,
            'type' => '/errors/410',
            'description' => 'Ce lien de partage a été révoqué',
        ]);
    }

    public function test_expired_share_returns_410(): void
    {
        ['token' => $token] = $this->setupShare(expiry: new \DateTimeImmutable('-1 hour'));

        $this->client->request('GET', '/api/shares/' . $token . '/download');

        $this->assertResponseStatusCodeSame(Response::HTTP_GONE);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/410',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce lien de partage a expiré',
            'status' => 410,
            'type' => '/errors/410',
            'description' => 'Ce lien de partage a expiré',
        ]);
    }

    public function test_password_required_returns_401_when_missing(): void
    {
        ['token' => $token] = $this->setupShare(passwordPlain: 'p@ss');

        $this->client->request('GET', '/api/shares/' . $token . '/download');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_password_correct_succeeds(): void
    {
        $this->client->disableReboot();
        ['token' => $token] = $this->setupShare(passwordPlain: 'p@ss');

        $this->client->request('GET', '/api/shares/' . $token . '/download?password=p%40ss');

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::FILE_CONTENT, $this->client->getInternalResponse()->getContent());
    }

    public function test_password_wrong_returns_401(): void
    {
        ['token' => $token] = $this->setupShare(passwordPlain: 'p@ss');

        $this->client->request('GET', '/api/shares/' . $token . '/download?password=wrong');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{bandSpace: \App\Entity\BandSpace\BandSpace, file: \App\Entity\BandSpace\BandSpaceFile, token: string, shareId: string, fileId: string}
     */
    private function setupShare(
        bool $revoke = false,
        ?\DateTimeImmutable $expiry = null,
        ?string $passwordPlain = null,
    ): array {
        $owner = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $owner,
            'originalName' => 'doc.txt',
        ])->create();

        $storagePath = 'share-test-' . bin2hex(random_bytes(4)) . '.txt';
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $owner,
            'mimeType' => 'text/plain',
            'size' => strlen(self::FILE_CONTENT),
            'storagePath' => $storagePath,
        ])->create();

        $file->currentVersion = $version;

        $token = bin2hex(random_bytes(16));
        $attributes = [
            'bandSpaceFile' => $file,
            'createdBy' => $owner,
            'tokenHash' => hash('sha256', $token),
            'expiryDatetime' => $expiry ?? new \DateTimeImmutable('+1 day'),
        ];
        if ($revoke) {
            $attributes['revocationDatetime'] = new \DateTimeImmutable('-1 hour');
        }
        if ($passwordPlain !== null) {
            $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(User::class);
            $attributes['passwordHash'] = $hasher->hash($passwordPlain);
        }
        $share = BandSpaceFileShareFactory::new($attributes)->create();

        self::getContainer()->get(EntityManagerInterface::class)->flush();

        /** @var FilesystemOperator $fs */
        $fs = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $fs->write('/band_space_files/' . $bandSpace->id . '/' . $storagePath, self::FILE_CONTENT);

        return [
            'bandSpace' => $bandSpace,
            'file' => $file,
            'token' => $token,
            'shareId' => $share->id,
            'fileId' => $file->id,
        ];
    }
}
