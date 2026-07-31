<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFilePublicShareMetadataTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_returns_metadata_for_unprotected_share(): void
    {
        // Pinned so expiry_datetime can be asserted exactly: the serializer formats it, not the provider.
        ['token' => $token] = $this->setupShare(expiry: new \DateTimeImmutable('2099-03-01T10:00:00+00:00'));

        $this->client->jsonRequest('GET', '/api/shares/' . $token . '/metadata', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFilePublicShareMetadata',
            '@id' => '/api/shares/' . $token . '/metadata',
            '@type' => 'BandSpaceFilePublicShareMetadata',
            'token' => $token,
            'original_name' => 'doc.txt',
            'size' => 42,
            'mime_type' => 'text/plain',
            'expiry_datetime' => '2099-03-01T10:00:00+00:00',
            'has_password' => false,
        ]);
    }

    public function test_returns_has_password_true_for_protected_share(): void
    {
        ['token' => $token] = $this->setupShare(passwordPlain: 'p@ss');

        $this->client->jsonRequest('GET', '/api/shares/' . $token . '/metadata', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertTrue($response['has_password']);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->client->jsonRequest('GET', '/api/shares/totally-fake-token/metadata', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

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

        $this->client->jsonRequest('GET', '/api/shares/' . $token . '/metadata', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    public function test_expired_share_returns_410(): void
    {
        ['token' => $token] = $this->setupShare(expiry: new \DateTimeImmutable('-1 hour'));

        $this->client->jsonRequest('GET', '/api/shares/' . $token . '/metadata', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_GONE);
    }

    /**
     * @return array{token: string}
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

        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'createdBy' => $owner,
            'mimeType' => 'text/plain',
            'size' => 42,
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
        BandSpaceFileShareFactory::new($attributes)->create();

        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return ['token' => $token];
    }
}
