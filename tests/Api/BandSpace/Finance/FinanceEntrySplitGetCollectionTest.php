<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\Role;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceEntrySplitFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class FinanceEntrySplitGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_splits_returns_existing_splits_for_a_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 300000,
        ])->create();

        // Splits come back ordered by creationDatetime ASC; pin distinct datetimes for a stable order.
        $adminSplit = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $adminMembership,
            'amount' => 100000,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        $memberSplit = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $memberMembership,
            'amount' => 200000,
            'creationDatetime' => new \DateTime('2024-01-02 10:00:00'),
        ])->create();

        // A plain (non-admin) member must get the splits, with member_id matching the membership ids.
        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntrySplit',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $adminSplit->id,
                    '@type' => 'FinanceEntrySplit',
                    'id' => (string) $adminSplit->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'entry_id' => (string) $entry->id,
                    'member_id' => (string) $adminMembership->id,
                    'member_name' => $admin->username,
                    'is_former_member' => false,
                    'amount' => 100000,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $memberSplit->id,
                    '@type' => 'FinanceEntrySplit',
                    'id' => (string) $memberSplit->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'entry_id' => (string) $entry->id,
                    'member_id' => (string) $memberMembership->id,
                    'member_name' => 'member_user',
                    'is_former_member' => false,
                    'amount' => 200000,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'update_datetime' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
