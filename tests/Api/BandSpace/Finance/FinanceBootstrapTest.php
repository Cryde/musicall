<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
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

class FinanceBootstrapTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_bootstrap_creates_default_categories(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $user = $user;
        $bandSpace = $bandSpace;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/bootstrap',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $categoryRepository = self::getContainer()->get(FinanceCategoryRepository::class);
        $categories = $categoryRepository->findByBandSpace($bandSpace);
        $this->assertCount(8, $categories);

        $names = array_map(fn ($cat) => $cat->name, $categories);
        $this->assertContains('Studio / Enregistrement', $names);
        $this->assertContains('Mix & Mastering', $names);
        $this->assertContains('Clips', $names);
        $this->assertContains('Communication & Promo', $names);
        $this->assertContains('Live & Concerts', $names);
        $this->assertContains('Matériel', $names);
        $this->assertContains('Identité visuelle', $names);
        $this->assertContains('Distribution & Admin', $names);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findBy(
            ['bandSpace' => $bandSpace, 'module' => BandSpaceModule::Finance],
        );
        $this->assertCount(1, $activities);
        $this->assertSame('categories_bootstrapped', $activities[0]->type);
        $this->assertSame(['count' => 8], $activities[0]->payload);
    }

    public function test_bootstrap_idempotent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Pre-create some categories to simulate bootstrap already ran
        FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Clips', 'position' => 1])->create();

        $user = $user;
        $bandSpace = $bandSpace;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/bootstrap',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Should still have only the 2 pre-existing categories, not 8 new ones
        $categoryRepository = self::getContainer()->get(FinanceCategoryRepository::class);
        $categories = $categoryRepository->findByBandSpace($bandSpace);
        $this->assertCount(2, $categories);
    }

    public function test_bootstrap_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $otherUser = $otherUser;
        $bandSpace = $bandSpace;

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/bootstrap',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_bootstrap_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $user = $user;
        $bandSpace = $bandSpace;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/bootstrap',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
