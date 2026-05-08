<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFolderCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_root_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
            ['name' => 'Setlists'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getResponseAsArray();
        $this->assertSame('Setlists', $response['name']);
        $this->assertNull($response['parent_id']);
        $this->assertSame(0, $response['depth']);
    }

    public function test_create_nested_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
            ['name' => '2026', 'parentId' => $parent->_real()->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getResponseAsArray();
        $this->assertSame('2026', $response['name']);
        $this->assertSame($parent->_real()->id, $response['parent_id']);
        $this->assertSame(1, $response['depth']);
    }

    public function test_create_rejects_duplicate_sibling_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Setlists'])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
            ['name' => 'setlists'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un dossier avec ce nom existe déjà à cet emplacement',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un dossier avec ce nom existe déjà à cet emplacement',
        ]);
    }

    public function test_create_rejects_max_depth_exceeded(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parent = null;
        for ($i = 0; $i < 6; $i++) {
            $parent = BandSpaceFolderFactory::new([
                'bandSpace' => $bandSpace,
                'createdBy' => $user,
                'parent' => $parent,
                'name' => sprintf('depth-%d', $i),
            ])->create();
        }

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
            ['name' => 'too-deep', 'parentId' => $parent->_real()->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La profondeur maximale (6) est dépassée',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'La profondeur maximale (6) est dépassée',
        ]);
    }

    public function test_create_persists_to_db(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
            ['name' => 'Riders'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $folders = $repo->findBy(['bandSpace' => $bandSpace->_real()]);
        $this->assertCount(1, $folders);
        $this->assertSame('Riders', $folders[0]->name);
    }
}
