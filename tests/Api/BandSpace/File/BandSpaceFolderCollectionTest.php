<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFolderCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_list_returns_empty_tree(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/folders',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFolder',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'virtualFolders' => [
                ['id' => 'virtual:task', 'name' => 'Tâches', 'source' => 'task', 'file_count' => 0],
                ['id' => 'virtual:finance', 'name' => 'Finances', 'source' => 'finance', 'file_count' => 0],
            ],
        ]);
    }

    public function test_list_returns_nested_tree(): void
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
        $paris = BandSpaceFolderFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'name' => 'paris',
            'parent' => $live2026,
            'creationDatetime' => new \DateTime('2026-04-03 10:00:00'),
        ])->create();
        $riders = BandSpaceFolderFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'name' => 'Riders',
            'creationDatetime' => new \DateTime('2026-04-04 10:00:00'),
        ])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/folders',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFolder',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders/' . $live->_real()->id,
                    '@type' => 'BandSpaceFolder',
                    'id' => $live->_real()->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Live',
                    'parent_id' => null,
                    'depth' => 0,
                    'children' => [
                        [
                            'id' => $live2026->_real()->id,
                            'band_space_id' => $bandSpaceId,
                            'name' => '2026',
                            'parent_id' => $live->_real()->id,
                            'depth' => 1,
                            'children' => [
                                [
                                    'id' => $paris->_real()->id,
                                    'band_space_id' => $bandSpaceId,
                                    'name' => 'paris',
                                    'parent_id' => $live2026->_real()->id,
                                    'depth' => 2,
                                    'children' => [],
                                    'creation_datetime' => '2026-04-03T10:00:00+00:00',
                                    'update_datetime' => null,
                                ],
                            ],
                            'creation_datetime' => '2026-04-02T10:00:00+00:00',
                            'update_datetime' => null,
                        ],
                    ],
                    'creation_datetime' => '2026-04-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders/' . $riders->_real()->id,
                    '@type' => 'BandSpaceFolder',
                    'id' => $riders->_real()->id,
                    'band_space_id' => $bandSpaceId,
                    'name' => 'Riders',
                    'parent_id' => null,
                    'depth' => 0,
                    'children' => [],
                    'creation_datetime' => '2026-04-04T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'virtualFolders' => [
                ['id' => 'virtual:task', 'name' => 'Tâches', 'source' => 'task', 'file_count' => 0],
                ['id' => 'virtual:finance', 'name' => 'Finances', 'source' => 'finance', 'file_count' => 0],
            ],
        ]);
    }

    public function test_list_virtual_folders_count_active_attached_files(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::uuid4(),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::uuid4(),
        ])->create();
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'finance',
            'attachedSourceId' => Uuid::uuid4(),
        ])->create();
        // Archived attached file — must not count
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => 'task',
            'attachedSourceId' => Uuid::uuid4(),
            'archiveDatetime' => new \DateTimeImmutable(),
        ])->create();
        // Manual file — must not appear in any virtual folder
        BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'attachedSourceType' => null,
            'attachedSourceId' => null,
        ])->create();

        $bandSpaceId = $bandSpace->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $bandSpaceId . '/folders',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFolder',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/folders',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'virtualFolders' => [
                ['id' => 'virtual:task', 'name' => 'Tâches', 'source' => 'task', 'file_count' => 2],
                ['id' => 'virtual:finance', 'name' => 'Finances', 'source' => 'finance', 'file_count' => 1],
            ],
        ]);
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
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders',
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
