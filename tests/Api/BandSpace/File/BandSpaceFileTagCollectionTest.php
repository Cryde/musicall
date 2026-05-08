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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFileTagCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_list_returns_empty_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
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
            'tags' => new ArrayCollection([$tagAcoustic->_real(), $tagSetlists->_real()]),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tagSetlists->_real()]),
        ])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
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
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagAcoustic->_real()->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagAcoustic->_real()->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Acoustic',
                    'color_hex' => '#0099CC',
                    'file_count' => 1,
                    'creation_datetime' => '2026-04-01T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagRiders->_real()->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagRiders->_real()->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Riders',
                    'color_hex' => null,
                    'file_count' => 0,
                    'creation_datetime' => '2026-04-02T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/tags/' . $tagSetlists->_real()->id,
                    '@type' => 'BandSpaceFileTag',
                    'id' => $tagSetlists->_real()->id,
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
            'tags' => new ArrayCollection([$tag->_real()]),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'tags' => new ArrayCollection([$tag->_real()]),
            'archiveDatetime' => new \DateTime('2026-05-01'),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tags',
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

        $this->client->loginUser($other->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tags',
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
