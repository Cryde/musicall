<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FinanceCategoryUpdateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_update_category_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Clips',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $category = $category->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id,
            ['name' => 'Concerts'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id,
            '@type' => 'FinanceCategory',
            'id' => $category->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Concerts',
            'parent_id' => null,
            'position' => 0,
            'has_children' => false,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $category->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_category_position(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Clips',
            'position' => 0,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $category = $category->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id,
            ['position' => 5],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id,
            '@type' => 'FinanceCategory',
            'id' => $category->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Clips',
            'parent_id' => null,
            'position' => 5,
            'has_children' => false,
            'creation_datetime' => $category->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $category->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }
}
