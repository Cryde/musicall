<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceEntrySplitGetItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_item(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntrySplit',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id,
            '@type' => 'FinanceEntrySplit',
            'id' => $split->id,
            'band_space_id' => $bandSpace->id,
            'entry_id' => $entry->id,
            'member_id' => $membership->id,
            'member_name' => $user->username,
            'is_former_member' => false,
            'amount' => 25000,
            'creation_datetime' => '2024-02-01T10:00:00+00:00',
            'update_datetime' => null,
        ]);
    }

    public function test_get_item_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/nonexistent-id');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_get_item_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_item_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
