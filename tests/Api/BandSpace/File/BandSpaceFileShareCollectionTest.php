<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileShareFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileShareCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_only_active_shares(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'doc.pdf'])->create();

        // Dates are pinned so the response body can be asserted whole: they are the only
        // fields the serializer formats itself rather than the builder handing it a string.
        $activeShare = BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $user,
            'tokenHash' => hash('sha256', 'token-active'),
            'expiryDatetime' => new \DateTimeImmutable('2099-03-01T10:00:00+00:00'),
            'lastAccessDatetime' => new \DateTimeImmutable('2026-02-14T08:30:00+00:00'),
            'accessCount' => 3,
            'creationDatetime' => new \DateTime('2026-02-01T09:15:00+00:00'),
        ])->create();
        // Revoked share
        BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $user,
            'tokenHash' => hash('sha256', 'token-revoked'),
            'expiryDatetime' => new \DateTimeImmutable('+1 day'),
            'revocationDatetime' => new \DateTimeImmutable('-1 hour'),
        ])->create();
        // Expired share
        BandSpaceFileShareFactory::new([
            'bandSpaceFile' => $file,
            'createdBy' => $user,
            'tokenHash' => hash('sha256', 'token-expired'),
            'expiryDatetime' => new \DateTimeImmutable('-1 hour'),
        ])->create();

        $bandSpaceId = $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/shares',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFileShare',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/shares',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    // Composite-identifier fallback IRI: the Delete template is not used to generate it.
                    '@id' => '/api/band_space_file_shares/id=' . $activeShare->id . ';bandSpaceId=' . $bandSpaceId,
                    '@type' => 'BandSpaceFileShare',
                    'id' => (string) $activeShare->id,
                    'band_space_id' => (string) $bandSpaceId,
                    'file_id' => (string) $file->id,
                    'file_original_name' => 'doc.pdf',
                    'expiry_datetime' => '2099-03-01T10:00:00+00:00',
                    'revocation_datetime' => null,
                    'access_count' => 3,
                    'last_access_datetime' => '2026-02-14T08:30:00+00:00',
                    'has_password' => false,
                    'is_active' => true,
                    'creation_datetime' => '2026-02-01T09:15:00+00:00',
                    'created_by' => [
                        'id' => (string) $user->id,
                        'username' => $user->username,
                    ],
                ],
            ],
        ]);
    }

    public function test_list_empty_returns_empty_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $bandSpaceId = $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/shares',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFileShare',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/shares',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_list_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/shares',
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
}
