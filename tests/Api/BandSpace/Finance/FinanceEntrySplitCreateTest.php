<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
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
use App\Validator\BandSpace\EntryNotPaidValidator;
use App\Validator\BandSpace\SplitNotPersonalValidator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FinanceEntrySplitCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_split(): void
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

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $membership = $membership->_real();
        $entry = $entry->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $splitRepository = self::getContainer()->get(FinanceEntrySplitRepository::class);
        $splits = $splitRepository->findByEntry($entry);
        $this->assertCount(1, $splits);

        $split = $splits[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntrySplit',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id,
            '@type' => 'FinanceEntrySplit',
            'id' => $split->id,
            'band_space_id' => $bandSpace->id,
            'entry_id' => $entry->id,
            'member_id' => (string) $membership->id,
            'member_name' => $user->username,
            'is_former_member' => false,
            'amount' => 25000,
            'creation_datetime' => $split->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_split_not_member(): void
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

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();
        $entry = $entry->_real();
        $membership = $membership->_real();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_split_inactive_member(): void
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

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $entry = $entry->_real();
        $membership = $membership->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_split_exceeds_entry_amount(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $secondUser = UserFactory::new()->create(['username' => 'second_user', 'email' => 'second@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership1 = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $membership2 = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $secondUser])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'scope' => FinanceEntryScope::Band,
            'amount' => 10000,
        ])->create();

        FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership1,
            'amount' => 8000,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $entry = $entry->_real();
        $membership2 = $membership2->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership2->id, 'amount' => 5000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_create_split_on_paid_entry_rejected(): void
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

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $membership = $membership->_real();
        $entry = $entry->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . EntryNotPaidValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
                    'code' => EntryNotPaidValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'description' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'type' => '/validation_errors/' . EntryNotPaidValidator::ERROR_CODE,
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_split_on_personal_entry(): void
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
            'label' => 'Mon achat perso',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Personal,
            'amount' => 80000,
            'member' => $membership,
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $membership = $membership->_real();
        $entry = $entry->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . SplitNotPersonalValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'La répartition n\'est pas disponible pour les entrées personnelles',
                    'code' => SplitNotPersonalValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'La répartition n\'est pas disponible pour les entrées personnelles',
            'description' => 'La répartition n\'est pas disponible pour les entrées personnelles',
            'type' => '/validation_errors/' . SplitNotPersonalValidator::ERROR_CODE,
            'title' => 'An error occurred',
        ]);
    }
}
