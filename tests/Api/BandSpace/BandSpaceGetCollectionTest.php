<?php

declare(strict_types=1);

namespace Api\BandSpace;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        // Use explicit creation dates to control order (DESC order, so newer first)
        $bandSpace1 = BandSpaceFactory::new([
            'name' => 'The Rockers',
            'creationDatetime' => new \DateTime('2024-01-01'),
        ])->create();
        $bandSpace2 = BandSpaceFactory::new([
            'name' => 'Jazz Ensemble',
            'creationDatetime' => new \DateTime('2024-01-02'),
        ])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace2, 'user' => $user])->create();

        $user = $user->_real();
        $bandSpace1 = $bandSpace1->_real();
        $bandSpace2 = $bandSpace2->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces');

        $this->assertResponseIsSuccessful();
        // Order is by creationDatetime DESC, so Jazz Ensemble (newer) comes first
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace2->id,
                    '@type' => 'BandSpace',
                    'id' => $bandSpace2->id,
                    'name' => 'Jazz Ensemble',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace1->id,
                    '@type' => 'BandSpace',
                    'id' => $bandSpace1->id,
                    'name' => 'The Rockers',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_collection_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_only_returns_user_band_spaces(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);

        $userBandSpace = BandSpaceFactory::new(['name' => 'My Band'])->create();
        $otherBandSpace = BandSpaceFactory::new(['name' => 'Other Band'])->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $userBandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBandSpace, 'user' => $otherUser])->create();

        $user = $user->_real();
        $userBandSpace = $userBandSpace->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpace',
            '@id' => '/api/band_spaces',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $userBandSpace->id,
                    '@type' => 'BandSpace',
                    'id' => $userBandSpace->id,
                    'name' => 'My Band',
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}
