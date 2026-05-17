<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceSummaryTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_summary_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceSummary',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            '@type' => 'FinanceSummary',
            'band_space_id' => $bandSpace->id,
            'current_membership_id' => $membership->id,
            'total_income' => 0,
            'total_expense' => 0,
            'total_planned' => 0,
            'total_committed' => 0,
            'total_paid' => 0,
            'total_personal' => 0,
            'has_estimates' => false,
            'min_date' => null,
            'max_date' => null,
            'by_category' => [],
            'member_contributions' => [],
            'upcoming_entries' => [],
        ]);
    }

    public function test_get_summary_with_entries(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-03-10'),
        ])->create();

        FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Crowdfunding income',
            'type' => FinanceEntryType::Income,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 15000,
            'date' => new \DateTime('2024-06-20'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceSummary',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            '@type' => 'FinanceSummary',
            'band_space_id' => $bandSpace->id,
            'current_membership_id' => $membership->id,
            'total_income' => 15000,
            'total_expense' => 50000,
            'total_planned' => 0,
            'total_committed' => 0,
            'total_paid' => 65000,
            'total_personal' => 0,
            'has_estimates' => false,
            'min_date' => '2024-03-10T00:00:00+00:00',
            'max_date' => '2024-06-20T00:00:00+00:00',
            'by_category' => [
                [
                    'id' => $category->id,
                    'name' => 'Studio',
                    'paid' => 65000,
                    'committed' => 0,
                    'planned' => 0,
                ],
            ],
            'member_contributions' => [],
            'upcoming_entries' => [],
        ]);
    }

    public function test_get_summary_with_estimated_entries(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage (devis en cours)',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => null,
            'amountMin' => 40000,
            'amountMax' => 60000,
            'date' => new \DateTime('2024-09-01'),
        ])->create();

        FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording exact',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 30000,
            'date' => new \DateTime('2024-02-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceSummary',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            '@type' => 'FinanceSummary',
            'band_space_id' => $bandSpace->id,
            'current_membership_id' => $membership->id,
            'total_income' => 0,
            'total_expense' => 30000,
            'total_planned' => 50000,
            'total_committed' => 0,
            'total_paid' => 30000,
            'total_personal' => 0,
            'has_estimates' => true,
            'min_date' => '2024-02-15T00:00:00+00:00',
            'max_date' => '2024-09-01T00:00:00+00:00',
            'by_category' => [
                [
                    'id' => $category->id,
                    'name' => 'Studio',
                    'paid' => 30000,
                    'committed' => 0,
                    'planned' => 50000,
                ],
            ],
            'member_contributions' => [],
            'upcoming_entries' => [],
        ]);
    }

    public function test_get_summary_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_summary_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/finance/summary',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
