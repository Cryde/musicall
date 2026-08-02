<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\TechRiderItem;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_tech_rider(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            ['name' => 'Technical rider 2026'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repository = self::getContainer()->get(TechRiderRepository::class);
        $riders = $repository->findByBandSpace($bandSpace);
        $this->assertCount(1, $riders);
        $rider = $riders[0];

        $items = self::getContainer()->get(TechRiderItemRepository::class)->findByRider($rider);

        // Ids and timestamps are generated, so the expected items are built from the rows;
        // the titles and the order are pinned separately below, which is the part that is a
        // product decision rather than an implementation detail.
        $expectedItems = array_map(
            static fn (TechRiderItem $item): array => [
                '@id' => '/api/band_spaces/' . $item->techRider->bandSpace->id
                    . '/tech_riders/' . $item->techRider->id
                    . '/items/' . $item->id,
                '@type' => 'TechRiderItem',
                'id' => (string) $item->id,
                'band_space_id' => (string) $bandSpace->id,
                'rider_id' => (string) $rider->id,
                'type' => 'text',
                'is_included' => true,
                'title' => $item->title,
                'content' => null,
                'position' => $item->position,
                'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
                'update_datetime' => null,
            ],
            $items,
        );

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
            '@type' => 'TechRider',
            'id' => $rider->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Technical rider 2026',
            'created_by_username' => $user->username,
            'archive_datetime' => null,
            'creation_datetime' => $rider->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'items' => $expectedItems,
            'item_count' => 7,
        ]);

        // A new rider opens on a prompt, not a blank page. The set and its order are the
        // product decision, so they are asserted literally rather than derived from the enum.
        $this->assertSame(
            [
                'Membres et contacts',
                'Backline et instruments',
                'Sonorisation',
                'Retours et in-ears',
                'Éclairage',
                'Catering',
                'Divers',
            ],
            array_map(static fn (TechRiderItem $item): string => $item->title, $items),
        );
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], array_map(
            static fn (TechRiderItem $item): int => $item->position,
            $items,
        ));

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Rider, $rider->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_created', $activities[0]->type);
        $this->assertSame(['name' => 'Technical rider 2026'], $activities[0]->payload);
    }

    public function test_create_tech_rider_name_required(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            ['name' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'name: Veuillez spécifier un nom',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'name: Veuillez spécifier un nom',
        ]);
    }

    public function test_create_tech_rider_name_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            ['name' => str_repeat('a', 256)],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom ne peut pas dépasser 255 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'name: Le nom ne peut pas dépasser 255 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'name: Le nom ne peut pas dépasser 255 caractères',
        ]);
    }

    public function test_create_tech_rider_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            ['name' => 'Rejected'],
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

    public function test_create_tech_rider_blocked_when_space_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            ['name' => 'Blocked'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }
}
