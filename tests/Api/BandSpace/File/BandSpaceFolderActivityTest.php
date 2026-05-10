<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceFolderActivityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_folder_records_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/folders',
            ['name' => 'Live'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $rows = $repo->findBy(['bandSpace' => $bandSpace, 'module' => BandSpaceModule::File]);
        $this->assertCount(1, $rows);
        $this->assertSame('folder_created', $rows[0]->type);
        $this->assertSame('Live', $rows[0]->payload['name']);
        $this->assertNull($rows[0]->payload['parent_id']);
    }

    public function test_rename_folder_records_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id,
            ['name' => 'Concerts'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $rows = $repo->findBy(['bandSpace' => $bandSpace, 'type' => 'folder_renamed']);
        $this->assertCount(1, $rows);
        $this->assertSame('Live', $rows[0]->payload['from']);
        $this->assertSame('Concerts', $rows[0]->payload['to']);
    }

    public function test_move_folder_records_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $live = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Live'])->create();
        $studio = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Studio'])->create();
        $sub = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Recordings', 'parent' => $live])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $sub->id,
            ['parent_id' => $studio->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();

        $repo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $rows = $repo->findBy(['bandSpace' => $bandSpace, 'type' => 'folder_moved']);
        $this->assertCount(1, $rows);
        $this->assertSame($live->id, $rows[0]->payload['from_parent_id']);
        $this->assertSame($studio->id, $rows[0]->payload['to_parent_id']);
    }

    public function test_delete_folder_records_activity_with_strategy(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Trash'])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/folders/' . $folder->id,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $rows = $repo->findBy(['bandSpace' => $bandSpace, 'type' => 'folder_archived']);
        $this->assertCount(1, $rows);
        $this->assertSame('Trash', $rows[0]->payload['name']);
        $this->assertSame('move_to_root', $rows[0]->payload['strategy']);
    }
}
