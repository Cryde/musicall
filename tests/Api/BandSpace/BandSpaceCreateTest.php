<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class BandSpaceCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function testCreateBandSpaceSuccess(): void
    {
        $bandSpaceRepository = self::getContainer()->get(BandSpaceRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();

        // Create an existing band space for this user to verify multiple band spaces work
        $existingBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $existingBandSpace, 'user' => $user])->create();

        // Verify one band space exists initially
        $result = $bandSpaceRepository->findByUser($user);
        $this->assertCount(1, $result);

        // Create band space
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            ['name' => 'The Rockers'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Verify band space was created (user now has 2 band spaces)
        $result = $bandSpaceRepository->findByUser($user);
        $this->assertCount(2, $result);

        $bandSpace = array_find($result, fn($bs): bool => $bs->name === 'The Rockers');
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'The Rockers',
            'role' => 'admin',
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $bandSpace->id);
        $this->assertCount(1, $activities);
        $this->assertSame('band_created', $activities[0]->type);
        $this->assertSame(['name' => 'The Rockers'], $activities[0]->payload);
        $this->assertSame($user->id, $activities[0]->actor?->id);
    }

    public function testCreateBandSpaceWithoutNameFails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            '@context' => '/api/contexts/ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'name: Veuillez spécifier un nom',
            'description' => 'name: Veuillez spécifier un nom',
            'status' => 422,
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
        ]);
    }

    public function testCreateBandSpaceWithoutAuthenticationFails(): void
    {
        $this->client->request(
            'POST',
            '/api/band_spaces',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
