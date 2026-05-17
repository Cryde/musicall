<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileTagCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_returns_empty_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $bandSpaceId = $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/tags',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFileTag',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_list_returns_tags_with_file_count(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tagAcoustic = BandSpaceFileTagFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Acoustic',
            'colorHex' => '#0099CC',
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();
        $tagRiders = BandSpaceFileTagFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Riders',
            'colorHex' => null,
            'creationDatetime' => new \DateTime('2026-04-02 10:00:00'),
        ])->create();
        $tagSetlists = BandSpaceFileTagFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Setlists',
            'colorHex' => '#FF6600',
            'creationDatetime' => new \DateTime('2026-04-03 10:00:00'),
        ])->create();

        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tagAcoustic, $tagSetlists]),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tagSetlists]),
        ])->create();

        $bandSpaceId = $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/tags',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFileTag',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagAcoustic->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagAcoustic->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Acoustic',
                    'color_hex' => '#0099CC',
                    'file_count' => 1,
                    'creation_datetime' => '2026-04-01T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagRiders->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagRiders->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Riders',
                    'color_hex' => null,
                    'file_count' => 0,
                    'creation_datetime' => '2026-04-02T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagSetlists->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagSetlists->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Setlists',
                    'color_hex' => '#FF6600',
                    'file_count' => 2,
                    'creation_datetime' => '2026-04-03T10:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_list_excludes_archived_files_from_count(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $tag = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'Riders'])->create();

        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tag]),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tag]),
            'archiveDatetime' => new \DateTime('2026-05-01'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tags',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertSame(1, $response['member'][0]['file_count']);
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
            '/api/band_spaces/' . $bandSpace->id . '/tags',
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
