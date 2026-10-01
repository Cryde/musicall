<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceEntrySplitFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Every finance amount is capped at 100 000 000 € (#1045). The columns used to be a signed INT, which
 * stops at 21 474 836,47 €, so the cap only holds because they are BIGINT now: a value at the cap is
 * read back from the database here, and the totals are summed above the old INT range.
 */
#[ResetDatabase]
class FinanceAmountLimitTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const int CAP = 10_000_000_000;
    private const int OVER_CAP = 10_000_000_001;
    private const string MESSAGE = 'Le montant ne peut pas dépasser 100 000 000 €';
    private const array JSON_LD = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array MERGE_PATCH = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private User $user;
    private BandSpace $bandSpace;
    private BandSpaceMembership $viewer;
    private FinanceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserFactory::new()->asBaseUser()->create();
        $this->bandSpace = BandSpaceFactory::new()->create();
        $this->viewer = BandSpaceMembershipFactory::new(['bandSpace' => $this->bandSpace, 'user' => $this->user])->create();
        $this->category = FinanceCategoryFactory::new([
            'bandSpace' => $this->bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
    }

    public function test_an_entry_above_the_cap_is_refused(): void
    {
        $this->postEntry(['amount' => self::OVER_CAP]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount: ' . self::MESSAGE,
            'description' => 'amount: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_estimate_above_the_cap_is_refused_on_both_bounds(): void
    {
        $this->postEntry(['amount_min' => self::OVER_CAP, 'amount_max' => self::OVER_CAP]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=' . LessThanOrEqual::TOO_HIGH_ERROR . ';1=' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_min',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
                [
                    'propertyPath' => 'amount_max',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount_min: ' . self::MESSAGE . "\namount_max: " . self::MESSAGE,
            'description' => 'amount_min: ' . self::MESSAGE . "\namount_max: " . self::MESSAGE,
            'type' => '/validation_errors/0=' . LessThanOrEqual::TOO_HIGH_ERROR . ';1=' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_entry_at_the_cap_is_stored_and_read_back_whole(): void
    {
        $this->postEntry(['amount' => self::CAP]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $entries = self::getContainer()->get(FinanceEntryRepository::class)->findByBandSpace($this->bandSpace, $this->viewer);
        $this->assertCount(1, $entries);
        $id = (string) $entries[0]->id;
        $entry = $this->reloadEntry($id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntry',
            '@id' => '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $id,
            '@type' => 'FinanceEntry',
            'id' => $id,
            'band_space_id' => $this->bandSpace->id,
            'category_id' => $this->category->id,
            'category_name' => 'Studio',
            'label' => 'Tournée',
            'type' => 'expense',
            'status' => 'planned',
            'amount' => self::CAP,
            'amount_min' => null,
            'amount_max' => null,
            'date' => '2024-01-15',
            'scope' => 'band',
            'member_id' => null,
            'member_name' => null,
            'recurrence_id' => null,
            'is_former_member' => false,
            'split_warning' => false,
            'creation_datetime' => $entry->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        // From the database, not the identity map: this is what proves the column holds the value.
        $this->assertSame(self::CAP, $entry->amount);
    }

    public function test_an_entry_cannot_be_raised_above_the_cap(): void
    {
        $entry = $this->createEntry(['amount' => 50000]);

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount' => self::OVER_CAP],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount: ' . self::MESSAGE,
            'description' => 'amount: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);

        $this->assertSame(50000, $this->reloadEntry((string) $entry->id)->amount);
    }

    public function test_an_estimate_cannot_be_widened_above_the_cap(): void
    {
        $entry = $this->createEntry(['amount' => null, 'amountMin' => 40000, 'amountMax' => 60000]);

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount_max' => self::OVER_CAP],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_max',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount_max: ' . self::MESSAGE,
            'description' => 'amount_max: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_recurrence_above_the_cap_is_refused(): void
    {
        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $this->category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => self::OVER_CAP,
                'startDate' => '2024-01-01',
                'endDate' => '2024-06-30',
            ],
            self::JSON_LD,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount: ' . self::MESSAGE,
            'description' => 'amount: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_recurrence_cannot_be_raised_above_the_cap(): void
    {
        $recurrence = $this->createRecurrence();

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/recurrences/' . $recurrence->id,
            ['amount' => self::OVER_CAP],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount: ' . self::MESSAGE,
            'description' => 'amount: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $this->assertSame(30000, $em->find(FinanceRecurrence::class, $recurrence->id)?->amount);
    }

    /** An estimate has no exact amount for the processor to check the shares against: only the cap bounds them. */
    public function test_a_share_of_an_estimate_is_capped_too(): void
    {
        $entry = $this->createEntry(['amount' => null, 'amountMin' => 40000, 'amountMax' => 60000, 'status' => FinanceEntryStatus::Committed]);
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $this->bandSpace])->create();

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => self::OVER_CAP],
            self::JSON_LD,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount: ' . self::MESSAGE,
            'description' => 'amount: ' . self::MESSAGE,
            'type' => '/validation_errors/' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_estimate_cannot_be_moved_above_the_cap_on_both_bounds(): void
    {
        $entry = $this->createEntry(['amount' => null, 'amountMin' => 40000, 'amountMax' => 60000]);

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id,
            ['amount_min' => self::OVER_CAP, 'amount_max' => self::OVER_CAP],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=' . LessThanOrEqual::TOO_HIGH_ERROR . ';1=' . LessThanOrEqual::TOO_HIGH_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount_min',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
                [
                    'propertyPath' => 'amount_max',
                    'message' => self::MESSAGE,
                    'code' => LessThanOrEqual::TOO_HIGH_ERROR,
                ],
            ],
            'detail' => 'amount_min: ' . self::MESSAGE . "\namount_max: " . self::MESSAGE,
            'description' => 'amount_min: ' . self::MESSAGE . "\namount_max: " . self::MESSAGE,
            'type' => '/validation_errors/0=' . LessThanOrEqual::TOO_HIGH_ERROR . ';1=' . LessThanOrEqual::TOO_HIGH_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /** The entries a recurrence generates copy its amount, so they are read back at the cap too. */
    public function test_a_recurrence_at_the_cap_is_stored_and_carried_to_its_entries(): void
    {
        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/recurrences',
            [
                'categoryId' => (string) $this->category->id,
                'label' => 'Loyer salle',
                'type' => 'expense',
                'scope' => 'band',
                'interval' => 'monthly',
                'amount' => self::CAP,
                'startDate' => '2024-01-01',
                'endDate' => '2024-01-31',
            ],
            self::JSON_LD,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $recurrence = $em->getRepository(FinanceRecurrence::class)->findOneBy(['label' => 'Loyer salle']);
        $this->assertInstanceOf(FinanceRecurrence::class, $recurrence);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => '/api/band_spaces/' . $this->bandSpace->id . '/finance/recurrences/' . $recurrence->id,
            '@type' => 'FinanceRecurrence',
            'id' => (string) $recurrence->id,
            'band_space_id' => $this->bandSpace->id,
            'category_id' => $this->category->id,
            'category_name' => 'Studio',
            'label' => 'Loyer salle',
            'type' => 'expense',
            'amount' => self::CAP,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => '2024-01-01T00:00:00+00:00',
            'end_date' => '2024-01-31T00:00:00+00:00',
            'is_active' => true,
            'entry_count' => 1,
            'updated_entry_count' => 0,
            'removed_entry_count' => 0,
            'created_entry_count' => 0,
            'creation_datetime' => $recurrence->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $this->assertSame(self::CAP, $recurrence->amount);
        $entries = $em->getRepository(FinanceEntry::class)->findBy(['recurrence' => $recurrence]);
        $this->assertSame([self::CAP], array_map(static fn (FinanceEntry $entry): ?int => $entry->amount, $entries));
    }

    public function test_a_share_of_an_estimate_at_the_cap_is_stored_and_read_back_whole(): void
    {
        $entry = $this->createEntry(['amount' => null, 'amountMin' => 40000, 'amountMax' => 60000, 'status' => FinanceEntryStatus::Committed]);
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $this->bandSpace])->create();

        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => self::CAP],
            self::JSON_LD,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $splits = self::getContainer()->get(FinanceEntrySplitRepository::class)->findByEntry($this->reloadEntry((string) $entry->id));
        $this->assertCount(1, $splits);
        $split = $splits[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntrySplit',
            '@id' => '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id,
            '@type' => 'FinanceEntrySplit',
            'id' => $split->id,
            'band_space_id' => $this->bandSpace->id,
            'entry_id' => $entry->id,
            'member_id' => (string) $membership->id,
            'member_name' => $membership->user->username,
            'is_former_member' => false,
            'amount' => self::CAP,
            'creation_datetime' => $split->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $this->assertSame(self::CAP, $split->amount);
    }

    public function test_the_totals_stay_right_above_the_old_int_range(): void
    {
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $this->bandSpace])->create();
        $tour = $this->createEntry(['label' => 'Tournée', 'amount' => self::CAP, 'status' => FinanceEntryStatus::Paid]);
        $this->createEntry([
            'label' => 'Cachets',
            'type' => FinanceEntryType::Income,
            'amount' => self::CAP,
            'status' => FinanceEntryStatus::Paid,
            'date' => new \DateTime('2024-02-20'),
        ]);
        $this->createEntry([
            'label' => 'Clip',
            'amount' => null,
            'amountMin' => self::CAP,
            'amountMax' => self::CAP,
            'status' => FinanceEntryStatus::Planned,
            'date' => new \DateTime('2024-03-10'),
        ]);
        FinanceEntrySplitFactory::new(['entry' => $tour, 'member' => $membership, 'amount' => self::CAP])->create();
        FinanceEntrySplitFactory::new([
            'entry' => $this->createEntry(['label' => 'Van', 'amount' => self::CAP, 'status' => FinanceEntryStatus::Committed, 'date' => new \DateTime('2024-01-20')]),
            'member' => $membership,
            'amount' => self::CAP,
        ])->create();

        $this->client->loginUser($this->user);
        $this->client->request('GET', '/api/band_spaces/' . $this->bandSpace->id . '/finance/summary', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceSummary',
            '@id' => '/api/band_spaces/' . $this->bandSpace->id . '/finance/summary',
            '@type' => 'FinanceSummary',
            'band_space_id' => $this->bandSpace->id,
            'current_membership_id' => $this->viewer->id,
            'total_income' => self::CAP,
            'total_expense' => self::CAP,
            'total_income_all' => self::CAP,
            'total_expense_all' => 3 * self::CAP,
            'total_planned' => self::CAP,
            'total_committed' => self::CAP,
            'total_paid' => 2 * self::CAP,
            'total_personal' => 0,
            'has_estimates' => true,
            'min_date' => '2024-01-15T00:00:00+00:00',
            'max_date' => '2024-03-10T00:00:00+00:00',
            'by_category' => [
                [
                    'id' => $this->category->id,
                    'name' => 'Studio',
                    'paid' => 2 * self::CAP,
                    'committed' => self::CAP,
                    'planned' => self::CAP,
                ],
            ],
            'member_contributions' => [
                [
                    'member_id' => (string) $membership->id,
                    'name' => $membership->user->username,
                    'total' => 2 * self::CAP,
                ],
            ],
            'upcoming_entries' => [],
        ]);
    }

    /** @param array<string, mixed> $fields */
    private function postEntry(array $fields): void
    {
        $this->client->loginUser($this->user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $this->bandSpace->id . '/finance/entries',
            [
                'categoryId' => (string) $this->category->id,
                'label' => 'Tournée',
                'type' => 'expense',
                'status' => 'planned',
                'scope' => 'band',
                'date' => '2024-01-15',
                ...$fields,
            ],
            self::JSON_LD,
        );
    }

    /** @param array<string, mixed> $fields */
    private function createEntry(array $fields): FinanceEntry
    {
        return FinanceEntryFactory::new([
            'category' => $this->category,
            'label' => 'Tournée',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'date' => new \DateTime('2024-01-15'),
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
            ...$fields,
        ])->create();
    }

    private function createRecurrence(): FinanceRecurrence
    {
        return FinanceRecurrenceFactory::new([
            'category' => $this->category,
            'label' => 'Loyer salle',
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Band,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 30000,
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-06-30'),
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
    }

    private function reloadEntry(string $id): FinanceEntry
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $entry = self::getContainer()->get(FinanceEntryRepository::class)->find($id);
        $this->assertInstanceOf(FinanceEntry::class, $entry);

        return $entry;
    }
}
