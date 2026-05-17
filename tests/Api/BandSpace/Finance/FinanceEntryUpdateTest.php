<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
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
     * @return iterable<string, array{0: string, 1: int|string|null}>
     */
    public static function paidEntryProtectedFieldProvider(): iterable
    {
        yield 'label' => ['label', 'Nouveau libellé'];
        yield 'amount' => ['amount', 99999];
        yield 'amount_min' => ['amount_min', 1000];
        yield 'amount_max' => ['amount_max', 2000];
        yield 'type' => ['type', 'income'];
        yield 'date' => ['date', '2024-12-31'];
        yield 'scope' => ['scope', 'personal'];
    }

    #[DataProvider('paidEntryProtectedFieldProvider')]
    public function test_update_paid_entry_each_protected_field_rejected(string $field, mixed $value): void
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
            [$field => $value],
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

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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
}
