<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Entity\BandSpace\FinanceCategory;
use App\Entity\BandSpace\FinanceEntry;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\FinanceAmountRangeValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceEntryUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_update_entry_label(): void
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
            'label' => 'Ancien libellé',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['label' => 'Nouveau libellé'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $updatedEntry = $entryRepository->find($entry->id);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Nouveau libellé',
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
            'update_datetime' => $updatedEntry->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_label_changed', $activities[0]->type);
        $this->assertSame(['from' => 'Ancien libellé', 'to' => 'Nouveau libellé'], $activities[0]->payload);
    }

    public function test_update_entry_status(): void
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'paid'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $updatedEntry = $entryRepository->find($entry->id);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Mixage',
            'type' => 'expense',
            'status' => 'paid',
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
            'update_datetime' => $updatedEntry->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_status_changed', $activities[0]->type);
        $this->assertSame(['from' => 'planned', 'to' => 'paid'], $activities[0]->payload);
    }

    public function test_update_entry_amount(): void
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

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount' => 75000],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $updatedEntry = $entryRepository->find($entry->id);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Mixage',
            'type' => 'expense',
            'status' => 'planned',
            'amount' => 75000,
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
            'update_datetime' => $updatedEntry->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('entry_amount_changed', $activities[0]->type);
        $this->assertSame(['from' => 50000, 'to' => 75000], $activities[0]->payload);
    }

    /**
     * Every payload here is one a non paid entry would accept, so what the test catches is the paid
     * lock rather than a constraint tripped on the way in. Which is why the fourchette arrives whole
     * and clears the exact amount: half a fourchette, or one sitting beside an amount, is now refused
     * by FinanceAmountRange before the processor ever looks at the status.
     *
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function paidEntryProtectedFieldProvider(): iterable
    {
        yield 'label' => [['label' => 'Nouveau libellé']];
        yield 'amount' => [['amount' => 99999]];
        yield 'amount range' => [['amount' => null, 'amount_min' => 1000, 'amount_max' => 2000]];
        yield 'type' => [['type' => 'income']];
        yield 'date' => [['date' => '2024-12-31']];
        yield 'scope' => [['scope' => 'personal']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('paidEntryProtectedFieldProvider')]
    public function test_update_paid_entry_each_protected_field_rejected(array $payload): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            $payload,
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
        ]);
    }

    public function test_update_paid_entry_member_id_rejected(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['member_id' => (string) $membership->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
        ]);
    }

    public function test_update_paid_entry_category_id_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $otherCategory = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Backline',
            'position' => 1,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['category_id' => (string) $otherCategory->id],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
        ]);
    }

    public function test_update_paid_entry_unlock_with_amount_in_one_patch(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'committed', 'amount' => 75000],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $updatedEntry = $entryRepository->find($entry->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            '@type' => 'FinanceEntry',
            'id' => $entry->id,
            'band_space_id' => $bandSpace->id,
            'category_id' => $category->id,
            'category_name' => 'Studio',
            'label' => 'Recording',
            'type' => 'expense',
            'status' => 'committed',
            'amount' => 75000,
            'amount_min' => null,
            'amount_max' => null,
            'date' => '2024-01-15',
            'scope' => 'band',
            'member_id' => null,
            'member_name' => null,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $updatedEntry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $updatedEntry->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_update_paid_entry_allows_status_change(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'committed'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
    }

    public function test_update_personal_entry_by_non_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $otherUser])->create();

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
            'member' => $ownerMembership,
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['label' => 'Tentative de modification'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        // 404 rather than the processor's 403 since somebody else's personal entry became invisible on
        // every operation: a 403 would confirm it exists, which is part of what is private about it.
        // The processor still refuses it too, as the layer behind this one.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Entrée introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Entrée introuvable',
        ]);
    }

    public function test_update_personal_entry_by_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mon achat perso',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Personal,
            'amount' => 80000,
            'member' => $ownerMembership,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $this->client->loginUser($owner);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['label' => 'Nouveau libellé perso'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
    }

    public function test_update_status_forbidden_transition_paid_to_planned(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'planned'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_update_status_allowed_transition_paid_to_committed(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'committed'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
    }

    public function test_update_status_allowed_transition_planned_to_paid(): void
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
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'paid'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
    }

    /**
     * A planned band entry of 500 EUR, the subject of the validation tests below. The PATCH endpoint
     * carried no constraint at all, so each of them was accepted, and the unparsable date reached
     * new DateTime() and came back as a 500.
     */
    private function createPlannedEntry(FinanceCategory $category): FinanceEntry
    {
        return FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mixage',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-15'),
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();
    }

    public function test_update_entry_with_a_negative_amount_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount' => -100],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . GreaterThanOrEqual::TOO_LOW_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => 'Le montant doit être positif ou zéro',
                    'code' => GreaterThanOrEqual::TOO_LOW_ERROR,
                ],
            ],
            'detail' => 'amount: Le montant doit être positif ou zéro',
            'description' => 'amount: Le montant doit être positif ou zéro',
            'type' => '/validation_errors/' . GreaterThanOrEqual::TOO_LOW_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_update_entry_with_a_minimum_above_its_maximum_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount' => null, 'amount_min' => 60000, 'amount_max' => 40000],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_MIN_MAX,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_min',
                    'message' => 'Le montant minimum doit être inférieur ou égal au montant maximum',
                    'code' => FinanceAmountRangeValidator::ERROR_CODE_MIN_MAX,
                ],
            ],
            'detail' => 'amount_min: Le montant minimum doit être inférieur ou égal au montant maximum',
            'description' => 'amount_min: Le montant minimum doit être inférieur ou égal au montant maximum',
            'type' => '/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_MIN_MAX,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * The merge-patch is validated on the entry as it would be after the write, so a fourchette sent
     * onto an entry that already carries an exact amount is caught even though the request never
     * mentions the amount.
     */
    public function test_update_entry_adding_a_range_next_to_its_exact_amount_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount_min' => 40000, 'amount_max' => 60000],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => 'Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
                    'code' => FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
                ],
            ],
            'detail' => 'amount: Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
            'description' => 'amount: Vous ne pouvez pas définir un montant exact et une fourchette en même temps',
            'type' => '/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_EXCLUSIVE,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * Half a fourchette is the shape that silently counted as zero: amount_min + amount_max is NULL as
     * soon as one side is, so the entry disappeared from every total without a warning anywhere.
     */
    public function test_update_entry_with_only_a_maximum_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount' => null, 'amount_max' => 60000],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_min',
                    'message' => 'Une fourchette doit avoir un montant minimum et un montant maximum',
                    'code' => FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
                ],
            ],
            'detail' => 'amount_min: Une fourchette doit avoir un montant minimum et un montant maximum',
            'description' => 'amount_min: Une fourchette doit avoir un montant minimum et un montant maximum',
            'type' => '/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * The deliberate cost of the new rule, pinned so it is a decision rather than a surprise. Rows
     * written before it could carry a single bound, and merge-patch validates the whole merged
     * object, so an untouched half fourchette now refuses even a PATCH that has nothing to do with
     * the amount. Its totals were wrong until the missing bound is filled in, so refusing is the
     * intended outcome, and the violation names the bound to fill.
     */
    public function test_update_a_legacy_entry_carrying_half_an_estimate_is_refused_until_it_is_completed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();

        // Written straight to the database, the way a row predating the rule looks.
        $legacy = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Cachet estimé',
            'type' => FinanceEntryType::Income,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => null,
            'amountMin' => 30000,
            'amountMax' => null,
            'date' => new \DateTime('2026-05-01'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $legacy->id,
            ['status' => 'committed'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_max',
                    'message' => 'Une fourchette doit avoir un montant minimum et un montant maximum',
                    'code' => FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
                ],
            ],
            'detail' => 'amount_max: Une fourchette doit avoir un montant minimum et un montant maximum',
            'description' => 'amount_max: Une fourchette doit avoir un montant minimum et un montant maximum',
            'type' => '/validation_errors/' . FinanceAmountRangeValidator::ERROR_CODE_INCOMPLETE,
            'title' => 'An error occurred',
        ]);
    }

    public function test_update_entry_with_a_blank_label_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['label' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'label',
                    'message' => 'Veuillez spécifier un libellé',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'label: Veuillez spécifier un libellé',
            'description' => 'label: Veuillez spécifier un libellé',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /** This one used to be a 500: the string went straight into new DateTime(). */
    public function test_update_entry_with_an_unparsable_date_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['date' => 'la semaine prochaine'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'date',
                    'message' => 'Le format de la date est invalide (attendu : AAAA-MM-JJ)',
                    'code' => Date::INVALID_FORMAT_ERROR,
                ],
            ],
            'detail' => 'date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'description' => 'date: Le format de la date est invalide (attendu : AAAA-MM-JJ)',
            'type' => '/validation_errors/' . Date::INVALID_FORMAT_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /** Also a 500 before: FinanceEntryStatus::from() on an unknown case. */
    public function test_update_entry_with_an_unknown_status_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = $this->createPlannedEntry($category);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id,
            ['status' => 'annule'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'Statut invalide',
                    'code' => Choice::NO_SUCH_CHOICE_ERROR,
                ],
            ],
            'detail' => 'status: Statut invalide',
            'description' => 'status: Statut invalide',
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }
}
