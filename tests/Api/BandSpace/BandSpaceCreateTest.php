<?php

declare(strict_types=1);

namespace Api\BandSpace;

use App\Repository\BandSpace\BandSpaceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function testCreateBandSpaceSuccess(): void
    {
        $bandSpaceRepository = self::getContainer()->get(BandSpaceRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();

        // Create an existing band space for this user to verify multiple band spaces work
        $existingBandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $existingBandSpace, 'user' => $user])->create();

        $user = $user->_real();

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

        $bandSpace = array_find($result, fn($bs) => $bs->name === 'The Rockers');
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces/' . $bandSpace->id,
            '@type' => 'BandSpace',
            'id' => $bandSpace->id,
            'name' => 'The Rockers',
        ]);
    }

    public function testCreateBandSpaceWithoutNameFails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $user = $user->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Veuillez spécifier un nom',
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
