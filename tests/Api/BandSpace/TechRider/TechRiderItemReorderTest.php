<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderItemPositions;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderItemReorderTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_reorder_applies_the_new_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();

        $first = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Un', 'position' => 0])->create();
        $second = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Deux', 'position' => 1])->create();
        $third = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Trois', 'position' => 2])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [
                ['id' => (string) $third->id, 'position' => 0],
                ['id' => (string) $first->id, 'position' => 1],
                ['id' => (string) $second->id, 'position' => 2],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $ordered = self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider);
        $this->assertSame(['Trois', 'Un', 'Deux'], array_map(
            static fn ($item): string => $item->title,
            $ordered,
        ));

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $rider->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_item_reordered', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'count' => 3], $activities[0]->payload);
    }

    /**
     * A partial payload is refused rather than applied. Renumbering only the items named
     * would leave the omitted ones holding positions that collide with the moved ones, so
     * the resulting order would depend on tie-breaking rather than on the request.
     */
    public function test_reorder_missing_an_item_is_rejected_and_changes_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $first = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Un', 'position' => 0])->create();
        $second = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Deux', 'position' => 1])->create();
        TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Trois', 'position' => 2])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [
                ['id' => (string) $second->id, 'position' => 0],
                ['id' => (string) $first->id, 'position' => 1],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Les positions doivent couvrir exactement les éléments de ce tech rider',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Les positions doivent couvrir exactement les éléments de ce tech rider',
        ]);

        $ordered = self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider);
        $this->assertSame(['Un', 'Deux', 'Trois'], array_map(
            static fn ($item): string => $item->title,
            $ordered,
        ));
    }

    public function test_reorder_with_a_item_from_another_rider_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $otherRider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $mine = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Un', 'position' => 0])->create();
        $theirs = TechRiderItemFactory::new(['techRider' => $otherRider, 'title' => 'Ailleurs', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [
                ['id' => (string) $mine->id, 'position' => 0],
                ['id' => (string) $theirs->id, 'position' => 1],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Les positions doivent couvrir exactement les éléments de ce tech rider',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Les positions doivent couvrir exactement les éléments de ce tech rider',
        ]);

        // The other rider's section keeps its position: a cross-rider payload writes nothing.
        $this->assertSame(0, self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $theirs->id, $otherRider)?->position);
    }

    /**
     * A duplicated id slips past both halves of the rule unless uniqueness is checked
     * explicitly: the contiguity check only looks at the position values, and the membership
     * check only sees the payload after it has been keyed by id, which silently collapses
     * the duplicate. The result would be a rider where no section holds position 0.
     */
    public function test_reorder_with_a_duplicated_item_is_rejected_and_changes_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $first = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Un', 'position' => 0])->create();
        $second = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Deux', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [
                ['id' => (string) $first->id, 'position' => 0],
                ['id' => (string) $second->id, 'position' => 1],
                ['id' => (string) $first->id, 'position' => 2],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemPositions::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'positions',
                    'message' => 'Chaque élément ne peut apparaître qu\'une seule fois',
                    'code' => TechRiderItemPositions::ERROR_CODE,
                ],
            ],
            'detail' => 'positions: Chaque élément ne peut apparaître qu\'une seule fois',
            'type' => '/validation_errors/' . TechRiderItemPositions::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'positions: Chaque élément ne peut apparaître qu\'une seule fois',
        ]);

        $ordered = self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider);
        $this->assertSame([0, 1], array_map(static fn ($item): int => $item->position, $ordered));
        $this->assertSame(['Un', 'Deux'], array_map(static fn ($item): string => $item->title, $ordered));
    }

    public function test_reorder_with_non_contiguous_positions_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $first = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Un', 'position' => 0])->create();
        $second = TechRiderItemFactory::new(['techRider' => $rider, 'title' => 'Deux', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [
                ['id' => (string) $first->id, 'position' => 0],
                ['id' => (string) $second->id, 'position' => 5],
            ]],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderItemPositions::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'positions',
                    'message' => 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
                    'code' => TechRiderItemPositions::ERROR_CODE,
                ],
            ],
            'detail' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
            'type' => '/validation_errors/' . TechRiderItemPositions::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'positions: Les positions doivent former une séquence 0..n-1 sans trou ni doublon',
        ]);
    }

    public function test_reorder_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $item = TechRiderItemFactory::new(['techRider' => $rider, 'position' => 0])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            $this->reorderUrl($bandSpace->id, $rider->id),
            ['positions' => [['id' => (string) $item->id, 'position' => 0]]],
            self::HEADERS,
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

    private function reorderUrl(string $bandSpaceId, string $riderId): string
    {
        return '/api/band_spaces/' . $bandSpaceId . '/tech_riders/' . $riderId . '/items/reorder';
    }
}
