<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Repository\BandSpace\FinanceCategoryRepository;
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

class FinanceCategoryCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            ['name' => 'Clips'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $categoryRepository = self::getContainer()->get(FinanceCategoryRepository::class);
        $categories = $categoryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $categories);

        $category = $categories[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $category->id,
            '@type' => 'FinanceCategory',
            'id' => $category->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Clips',
            'parent_id' => null,
            'position' => 0,
            'has_children' => false,
            'creation_datetime' => $category->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_category_with_parent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $parentCategory = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Production',
            'position' => 0,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $parentCategory = $parentCategory->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            ['name' => 'Studio', 'parent_id' => $parentCategory->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $categoryRepository = self::getContainer()->get(FinanceCategoryRepository::class);
        $categories = $categoryRepository->findByBandSpace($bandSpace);
        // Find the child category (the one with a parent)
        $child = null;
        foreach ($categories as $cat) {
            if ($cat->parent !== null) {
                $child = $cat;
                break;
            }
        }
        $this->assertNotNull($child);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/categories/' . $child->id,
            '@type' => 'FinanceCategory',
            'id' => $child->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Studio',
            'parent_id' => $parentCategory->id,
            'position' => 0,
            'has_children' => false,
            'creation_datetime' => $child->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_category_exceeds_max_depth(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Level 1 (pole)
        $pole = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Production',
            'position' => 0,
        ])->create();

        // Level 2 (child)
        $child = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'parent' => $pole,
            'position' => 0,
        ])->create();

        // Try to create level 3 (grandchild under child) — should fail
        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/finance/categories',
            ['name' => 'Mixage', 'parent_id' => (string) $child->_real()->id],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_f1a2b3c4-d5e6-7890-abcd-ef1234567890',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'parent_id',
                    'message' => 'La profondeur maximale de 2 niveaux est atteinte',
                    'code' => 'music_all_f1a2b3c4-d5e6-7890-abcd-ef1234567890',
                ],
            ],
            'detail' => 'parent_id: La profondeur maximale de 2 niveaux est atteinte',
            'description' => 'parent_id: La profondeur maximale de 2 niveaux est atteinte',
            'type' => '/validation_errors/music_all_f1a2b3c4-d5e6-7890-abcd-ef1234567890',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_category_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            ['name' => 'Forbidden Category'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_category_inactive_member(): void
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
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
            ['name' => 'Forbidden Category'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_category_empty_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/categories',
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
            'description' => 'name: Veuillez spécifier un nom',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }
}
