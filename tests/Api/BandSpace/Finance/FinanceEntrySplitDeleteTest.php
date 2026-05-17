<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceEntrySplitFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceEntrySplitDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_split(): void
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
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();
        $splitId = (string) $split->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $splitId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $splitRepository = self::getContainer()->get(FinanceEntrySplitRepository::class);
        $this->assertNull($splitRepository->find($splitId));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('split_removed', $activities[0]->type);
        $this->assertSame($splitId, $activities[0]->payload['split_id'] ?? null);
        $this->assertSame(25000, $activities[0]->payload['amount'] ?? null);
    }

    public function test_delete_split_not_member(): void
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
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_split_on_paid_entry_rejected(): void
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
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Impossible de supprimer une répartition d\'une entrée payée. Repassez le statut à Engagé.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Impossible de supprimer une répartition d\'une entrée payée. Repassez le statut à Engagé.',
        ]);
    }

    public function test_delete_split_inactive_member(): void
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
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $split = FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership,
            'amount' => 25000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
