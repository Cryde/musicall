<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceFolderItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /**
     * The item endpoint counts a folder's files with a different query from the collection's grouped
     * one, so a folder read on its own could report a subtree roll-up, or zero, with the collection
     * test staying green.
     */
    public function test_get_returns_the_folder_with_the_files_directly_in_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $live = BandSpaceFolderFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'name' => 'Live',
            'creationDatetime' => new \DateTime('2026-04-01 10:00:00'),
        ])->create();
        $live2026 = BandSpaceFolderFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'name' => '2026',
            'parent' => $live,
            'creationDatetime' => new \DateTime('2026-04-02 10:00:00'),
        ])->create();

        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $live2026])->create();
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $live2026])->create();
        // In the parent, so the child must not count it.
        BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $live])->create();
        // In the bin, so nothing lists it and nothing counts it.
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'folder' => $live2026,
            'archiveDatetime' => new \DateTimeImmutable(),
        ])->create();

        $bandSpaceId = $bandSpace->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $live2026->id,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFolder',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders/' . $live2026->id,
            '@type' => 'BandSpaceFolder',
            'id' => $live2026->id,
            'band_space_id' => $bandSpaceId,
            'name' => '2026',
            'parent_id' => $live->id,
            'depth' => 1,
            'file_count' => 2,
            'children' => [],
            'creation_datetime' => '2026-04-02T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    public function test_get_unknown_folder_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/folders/0198c0de-dead-beef-cafe-000000000001',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Dossier introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Dossier introuvable',
        ]);
    }
}
