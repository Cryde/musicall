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
use App\Tests\Factory\BandSpace\FinanceEntrySplitFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FinanceEntryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_entries(): void
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

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $user = $user;
        $bandSpace = $bandSpace;
        $category = $category;
        $entry = $entry;

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
                    '@type' => 'FinanceEntry',
                    'id' => $entry->id,
                    'band_space_id' => $bandSpace->id,
                    'category_id' => $category->id,
                    'category_name' => 'Studio',
                    'label' => 'Mixage',
                    'type' => 'expense',
                    'status' => 'planned',
                    'amount' => 50000,
                    'amount_min' => null,
                    'amount_max' => null,
                    'date' => '2024-01-15',
                    'scope' => 'band',
                    'member_id' => null,
                    'member_name' => null,
                    'recurrence_id' => null,
                    'is_former_member' => false,
                    'split_warning' => false,
                    'creation_datetime' => '2024-02-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_entries_with_split_warning(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 10000,
            'date' => new \DateTime('2024-01-15'),
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 5000,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $user = $user;
        $bandSpace = $bandSpace;
        $category = $category;
        $entry = $entry;

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
                    '@type' => 'FinanceEntry',
                    'id' => $entry->id,
                    'band_space_id' => $bandSpace->id,
                    'category_id' => $category->id,
                    'category_name' => 'Studio',
                    'label' => 'Mixage',
                    'type' => 'expense',
                    'status' => 'planned',
                    'amount' => 10000,
                    'amount_min' => null,
                    'amount_max' => null,
                    'date' => '2024-01-15',
                    'scope' => 'band',
                    'member_id' => null,
                    'member_name' => null,
                    'recurrence_id' => null,
                    'is_former_member' => false,
                    'split_warning' => true,
                    'creation_datetime' => '2024-02-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_entries_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $otherUser = $otherUser;
        $bandSpace = $bandSpace;

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_entries_inactive_member(): void
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
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
