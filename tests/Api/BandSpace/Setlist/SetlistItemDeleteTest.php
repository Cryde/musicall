<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\SetlistItemType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistItemDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_item_collapses_trailing_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $itemA = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'A', 'position' => 0])->create();
        $itemB = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'B', 'position' => 1])->create();
        $itemC = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'C', 'position' => 2])->create();
        $itemBId = (string) $itemB->id;

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $itemBId
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $bandSpaceId = (string) $bandSpace->id;
        $setlistId = (string) $setlist->id;
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $itemRepo = self::getContainer()->get(SetlistItemRepository::class);
        $this->assertNull($itemRepo->find($itemBId));
        $this->assertSame(0, $itemRepo->find((string) $itemA->id)->position);
        $this->assertSame(1, $itemRepo->find((string) $itemC->id)->position);

        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find($bandSpaceId);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Setlist, $setlistId);
        $this->assertCount(1, $activities);
        $this->assertSame('setlist_item_removed', $activities[0]->type);
    }

    public function test_delete_item_not_found_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Item introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Item introuvable',
        ]);
    }

    public function test_delete_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $item = SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'L',
            'position' => 0,
        ])->create();

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/items/' . $item->id
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
