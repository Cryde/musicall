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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
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
            'deletion_scheduled_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $bandSpace->id);
        $this->assertCount(1, $activities);
        $this->assertSame('band_created', $activities[0]->type);
        $this->assertSame(['name' => 'The Rockers'], $activities[0]->payload);
        $this->assertSame($user->id, $activities[0]->actor?->id);
    }

    public function test_a_padded_name_is_stored_trimmed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            ['name' => '   The Rockers   '],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $result = self::getContainer()->get(BandSpaceRepository::class)->findByUser($user);
        $this->assertCount(1, $result);
        $bandSpace = $result[0];
        $this->assertSame('The Rockers', $bandSpace->name);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'The Rockers',
            'role' => 'admin',
            'deletion_scheduled_datetime' => null,
        ]);

        // The activity feed reads the stored name, so it must not keep a copy of the padded input.
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Settings, $bandSpace->id);
        $this->assertCount(1, $activities);
        $this->assertSame(['name' => 'The Rockers'], $activities[0]->payload);
    }

    /**
     * The case the trim normalizer exists for: padding must not buy a name its way past the minimum,
     * because the padding is gone by the time the processor stores it. The rename path refuses this
     * name, so creation has to refuse it too, otherwise the space can never be renamed to it.
     */
    public function test_a_padded_name_that_trims_below_the_minimum_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            ['name' => '  ab  '],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'name: Le nom doit contenir au moins 3 caractères',
            'description' => 'name: Le nom doit contenir au moins 3 caractères',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom doit contenir au moins 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);

        $this->assertCount(0, self::getContainer()->get(BandSpaceRepository::class)->findByUser($user));
    }

    public function test_a_whitespace_only_name_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            ['name' => '   '],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => "name: Veuillez spécifier un nom\nname: Le nom doit contenir au moins 3 caractères",
            'description' => "name: Veuillez spécifier un nom\nname: Le nom doit contenir au moins 3 caractères",
            'status' => 422,
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'name',
                    'message' => 'Le nom doit contenir au moins 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);

        $this->assertCount(0, self::getContainer()->get(BandSpaceRepository::class)->findByUser($user));
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
