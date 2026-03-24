<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FinanceCategoryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_categories(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category1 = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Clips',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $category2 = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Concerts',
            'position' => 1,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $category1 = $category1->_real();
        $category2 = $category2->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category1->id,
                    '@type' => 'FinanceCategory',
                    'id' => $category1->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Clips',
                    'parent_id' => null,
                    'position' => 0,
                    'has_children' => false,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category2->id,
                    '@type' => 'FinanceCategory',
                    'id' => $category2->id,
                    'band_space_id' => $bandSpace->id,
                    'name' => 'Concerts',
                    'parent_id' => null,
                    'position' => 1,
                    'has_children' => false,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_categories_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_categories_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/categories');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
