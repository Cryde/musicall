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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFileQuotaTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const int FIVE_GB = 5_368_709_120;

    public function test_quota_endpoint_returns_default_env_quota_for_empty_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/files/quota',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFileQuota',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/files/quota',
            '@type' => 'BandSpaceFileQuota',
            'band_space_id' => $bandSpaceId,
            'quota_bytes' => self::FIVE_GB,
            'used_bytes' => 0,
            'used_percentage' => 0.0,
            'is_approaching_limit' => false,
            'breakdown_by_source' => [],
        ]);
    }

    public function test_quota_endpoint_honours_per_band_override(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 1024])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files/quota',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1024, $response['quota_bytes']);
    }

    public function test_quota_endpoint_sums_versions_excludes_archived_groups_by_source(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 10_000])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Manual file with two versions: 100 + 200 = 300
        $manualFile = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $manualFile, 'versionNumber' => 1, 'size' => 100])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $manualFile, 'versionNumber' => 2, 'size' => 200])->create();

        // Task-attached file: 50
        $taskFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
        ])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $taskFile, 'versionNumber' => 1, 'size' => 50])->create();

        // Archived file with two versions: should be EXCLUDED
        $archivedFile = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('-1 hour'),
        ])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $archivedFile, 'versionNumber' => 1, 'size' => 5_000])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $archivedFile, 'versionNumber' => 2, 'size' => 5_000])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files/quota',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(350, $response['used_bytes']);
        $this->assertSame(3.5, $response['used_percentage']);
        $this->assertFalse($response['is_approaching_limit']);
        $this->assertEqualsCanonicalizing(
            [
                ['source' => 'manual', 'bytes' => 300],
                ['source' => 'task', 'bytes' => 50],
            ],
            $response['breakdown_by_source'],
        );
    }

    public function test_quota_endpoint_flags_approaching_limit_at_80_percent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 100])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $file, 'versionNumber' => 1, 'size' => 80])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files/quota',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(80, $response['used_bytes']);
        $this->assertSame(80.0, (float) $response['used_percentage']);
        $this->assertTrue($response['is_approaching_limit']);
    }

    public function test_quota_endpoint_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($other->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files/quota',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
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

    public function test_upload_at_quota_limit_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 100])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $existing = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $existing, 'versionNumber' => 1, 'size' => 80])->create();

        // sample.txt is 30 bytes; 80 + 30 = 110 > 100 → 422
        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $this->getResponseAsArray();
        $this->assertSame('Error', $response['@type']);
        $this->assertStringContainsString('Quota de stockage dépassé', $response['detail']);
    }

    public function test_upload_crossing_80_percent_sets_quota_approaching_header(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 100])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $existing = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileVersionFactory::new(['bandSpaceFile' => $existing, 'versionNumber' => 1, 'size' => 60])->create();

        // sample.txt is 30 bytes; 60 + 30 = 90 < 100 (allowed) but 90/100 >= 0.80 → warning header
        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('true', $this->client->getResponse()->headers->get('X-Quota-Approaching'));
    }

    public function test_upload_well_below_threshold_does_not_set_warning_header(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 10_000])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertNull($this->client->getResponse()->headers->get('X-Quota-Approaching'));
    }

    public function test_version_upload_blocked_when_over_quota_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['quotaBytesOverride' => 100])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $v1 = BandSpaceFileVersionFactory::new(['bandSpaceFile' => $file, 'versionNumber' => 1, 'size' => 80])->create();

        $file->_real()->currentVersion = $v1->_real();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/files/' . $file->_real()->id . '/versions',
            [],
            ['uploadedFile' => new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $this->getResponseAsArray();
        $this->assertStringContainsString('Quota de stockage dépassé', $response['detail']);
    }
}
