<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceRecurrenceGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_recurrences(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrence1 = FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Loyer salle',
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Band,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 50000,
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-06-30'),
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrence2 = FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Assurance',
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Band,
            'interval' => RecurrenceInterval::Yearly,
            'amount' => 120000,
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2026-12-31'),
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence2->id,
                    '@type' => 'FinanceRecurrence',
                    'id' => $recurrence2->id,
                    'band_space_id' => $bandSpace->id,
                    'category_id' => $category->id,
                    'category_name' => 'Studio',
                    'label' => 'Assurance',
                    'type' => 'expense',
                    'amount' => 120000,
                    'scope' => 'band',
                    'interval' => 'yearly',
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2026-12-31T00:00:00+00:00',
                    'is_active' => true,
                    'entry_count' => 0,
                    'creation_datetime' => '2024-02-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence1->id,
                    '@type' => 'FinanceRecurrence',
                    'id' => $recurrence1->id,
                    'band_space_id' => $bandSpace->id,
                    'category_id' => $category->id,
                    'category_name' => 'Studio',
                    'label' => 'Loyer salle',
                    'type' => 'expense',
                    'amount' => 50000,
                    'scope' => 'band',
                    'interval' => 'monthly',
                    'start_date' => '2024-01-01T00:00:00+00:00',
                    'end_date' => '2024-06-30T00:00:00+00:00',
                    'is_active' => true,
                    'entry_count' => 0,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_recurrences_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_recurrences_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
