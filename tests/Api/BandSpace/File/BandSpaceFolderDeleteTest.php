<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceFolderDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_default_strategy_moves_children_to_root(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => '2026', 'parent' => $parent])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'folder' => $parent])->create();

        $bandSpaceId = $bandSpace->_real()->id;
        $parentId = $parent->_real()->id;
        $childId = $child->_real()->id;
        $fileId = $file->_real()->id;

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parentId,
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();

        /** @var BandSpaceFolderRepository $folderRepo */
        $folderRepo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $this->assertNull($folderRepo->find($parentId));
        $reloadedChild = $folderRepo->find($childId);
        $this->assertNotNull($reloadedChild);
        $this->assertNull($reloadedChild->parent);

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        $reloadedFile = $fileRepo->find($fileId);
        $this->assertNotNull($reloadedFile);
        $this->assertNull($reloadedFile->folder);
        $this->assertNull($reloadedFile->archiveDatetime);
    }

    public function test_delete_cascade_strategy_admin_archives_subtree_files(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'admin', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $parent = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => 'Live'])->create();
        $child = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'name' => '2026', 'parent' => $parent])->create();
        $fileInParent = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $parent])->create();
        $fileInChild = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $admin, 'folder' => $child])->create();

        $bandSpaceId = $bandSpace->_real()->id;
        $parentId = $parent->_real()->id;
        $childId = $child->_real()->id;
        $fileIds = [$fileInParent->_real()->id, $fileInChild->_real()->id];

        $this->client->loginUser($admin->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpaceId . '/folders/' . $parentId . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get('doctrine')->getManager()->clear();

        /** @var BandSpaceFolderRepository $folderRepo */
        $folderRepo = self::getContainer()->get(BandSpaceFolderRepository::class);
        $this->assertNull($folderRepo->find($parentId));
        $this->assertNull($folderRepo->find($childId));

        /** @var BandSpaceFileRepository $fileRepo */
        $fileRepo = self::getContainer()->get(BandSpaceFileRepository::class);
        foreach ($fileIds as $fileId) {
            $reloaded = $fileRepo->find($fileId);
            $this->assertNotNull($reloaded);
            $this->assertNotNull($reloaded->archiveDatetime);
        }
    }

    public function test_delete_cascade_strategy_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member, 'name' => 'Live'])->create();

        $this->client->loginUser($member->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/folders/' . $folder->_real()->id . '?strategy=cascade',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul un administrateur peut supprimer un dossier en cascade',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Seul un administrateur peut supprimer un dossier en cascade',
        ]);
    }
}
