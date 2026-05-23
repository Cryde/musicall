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
class SetlistReorderTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_reorder_items_updates_positions(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $itemA = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'A', 'position' => 0])->create();
        $itemB = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'B', 'position' => 1])->create();
        $itemC = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'C', 'position' => 2])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/reorder',
            [
                'positions' => [
                    ['id' => (string) $itemC->id, 'position' => 0],
                    ['id' => (string) $itemA->id, 'position' => 1],
                    ['id' => (string) $itemB->id, 'position' => 2],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $bandSpaceId = (string) $bandSpace->id;
        $setlistId = (string) $setlist->id;
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $itemRepo = self::getContainer()->get(SetlistItemRepository::class);
        $this->assertSame(1, $itemRepo->find((string) $itemA->id)->position);
        $this->assertSame(2, $itemRepo->find((string) $itemB->id)->position);
        $this->assertSame(0, $itemRepo->find((string) $itemC->id)->position);

        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find($bandSpaceId);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Setlist, $setlistId);
        $this->assertCount(1, $activities, 'Reorder must record one activity row, not one per item');
        $this->assertSame('setlist_item_reordered', $activities[0]->type);
    }

    public function test_reorder_validation_not_contiguous(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $itemA = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'A', 'position' => 0])->create();
        $itemB = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'B', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/reorder',
            [
                'positions' => [
                    ['id' => (string) $itemA->id, 'position' => 0],
                    ['id' => (string) $itemB->id, 'position' => 5],
                ],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . \App\Validator\BandSpace\Setlist\SetlistReorderPositions::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'positions',
                    'message' => 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
                    'code' => \App\Validator\BandSpace\Setlist\SetlistReorderPositions::ERROR_CODE,
                ],
            ],
            'detail' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
            'type' => '/validation_errors/' . \App\Validator\BandSpace\Setlist\SetlistReorderPositions::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
        ]);
    }

    public function test_reorder_partial_set_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $itemA = SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'A', 'position' => 0])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'B', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/reorder',
            ['positions' => [['id' => (string) $itemA->id, 'position' => 0]]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le réordonnancement doit inclure tous les items du setlist',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Le réordonnancement doit inclure tous les items du setlist',
        ]);
    }

    public function test_reorder_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/reorder',
            ['positions' => [['id' => '00000000-0000-0000-0000-000000000000', 'position' => 0]]],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
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
