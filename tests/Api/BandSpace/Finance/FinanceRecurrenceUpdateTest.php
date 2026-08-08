<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\RecurrenceEndDateValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Editing a recurrence has to reach the entries it already materialised.
 *
 * The occurrence grid is anchored on the first of a month relative to today, so "already due" and "still
 * to come" stay unambiguous whatever day the suite runs on.
 */
#[ResetDatabase]
class FinanceRecurrenceUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string LABEL = 'Loyer salle';
    private const string MERGE_PATCH_CONTENT_TYPE = 'application/merge-patch+json';

    public function test_raising_the_amount_rewrites_the_future_forecasts_and_spares_the_rest(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));

        $paid = $this->createEntry($category, $recurrence, self::monthStart(-3), FinanceEntryStatus::Paid);
        $pastPlanned = $this->createEntry($category, $recurrence, self::monthStart(-1), FinanceEntryStatus::Planned);
        $committed = $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Committed);
        $futurePlanned = $this->createEntry($category, $recurrence, self::monthStart(2), FinanceEntryStatus::Planned);
        $lastPlanned = $this->createEntry($category, $recurrence, self::monthStart(3), FinanceEntryStatus::Planned);

        $recurrenceId = (string) $recurrence->id;
        $entryIds = [
            'paid' => (string) $paid->id,
            'past_planned' => (string) $pastPlanned->id,
            'committed' => (string) $committed->id,
            'future_planned' => (string) $futurePlanned->id,
            'last_planned' => (string) $lastPlanned->id,
        ];

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['amount' => 35000]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId);
        $this->assertCount(1, $activities);
        $this->assertSame('recurrence_updated', $activities[0]->type);
        $this->assertSame(['changed_fields' => ['amount']], $activities[0]->payload);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 35000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-3)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 5,
            'updated_entry_count' => 2,
            'removed_entry_count' => 0,
            'created_entry_count' => 0,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        $entries = $this->reloadEntries($entryIds);
        $this->assertSame(30000, $entries['paid']->amount, 'A paid entry is accounting history');
        $this->assertSame(30000, $entries['past_planned']->amount, 'A forecast already due is not repriced');
        $this->assertSame(30000, $entries['committed']->amount, 'A committed occurrence was engaged at its own amount');
        $this->assertSame(35000, $entries['future_planned']->amount);
        $this->assertSame(35000, $entries['last_planned']->amount);
    }

    public function test_deactivating_removes_the_future_forecasts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));

        $paid = $this->createEntry($category, $recurrence, self::monthStart(-3), FinanceEntryStatus::Paid);
        $pastPlanned = $this->createEntry($category, $recurrence, self::monthStart(-1), FinanceEntryStatus::Planned);
        $committed = $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Committed);
        $this->createEntry($category, $recurrence, self::monthStart(2), FinanceEntryStatus::Planned);
        $this->createEntry($category, $recurrence, self::monthStart(3), FinanceEntryStatus::Planned);

        $recurrenceId = (string) $recurrence->id;
        $survivorIds = [(string) $paid->id, (string) $pastPlanned->id, (string) $committed->id];

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['is_active' => false]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId);
        $this->assertCount(1, $activities);
        $this->assertSame('recurrence_stopped', $activities[0]->type);
        $this->assertNull($activities[0]->payload);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-3)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => false,
            'entry_count' => 3,
            'updated_entry_count' => 0,
            'removed_entry_count' => 2,
            'created_entry_count' => 0,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        $this->assertSame($survivorIds, $this->remainingEntryIds($recurrenceId));
    }

    public function test_toggling_active_off_then_on_restores_the_forecasts_without_duplicating(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(3));

        // The state a deactivation leaves behind, seeded rather than requested: the api firewall is
        // stateless so only one authenticated request is available, and it belongs to the
        // reactivation under test. Deactivation itself is covered by its own case above.
        $this->createEntry($category, $recurrence, self::monthStart(-2), FinanceEntryStatus::Planned);
        $this->createEntry($category, $recurrence, self::monthStart(-1), FinanceEntryStatus::Paid);
        $this->createEntry($category, $recurrence, self::monthStart(0), FinanceEntryStatus::Planned);
        $this->createEntry($category, $recurrence, self::monthStart(2), FinanceEntryStatus::Committed);
        $recurrence->isActive = false;
        \Zenstruck\Foundry\Persistence\save($recurrence);

        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['is_active' => true]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-2)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 6,
            'updated_entry_count' => 0,
            'removed_entry_count' => 0,
            'created_entry_count' => 2,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        // The date the committed entry sits on is skipped rather than doubled, so the round trip lands
        // back on exactly the grid it started from.
        $this->assertSame($this->gridDates(-2, 3), $this->remainingEntryDates($recurrenceId));
    }

    /**
     * The symptom this issue is about, reached through the other door: a stopped recurrence must not
     * start filling the budget again just because somebody pushed its end date out.
     */
    public function test_extending_a_stopped_recurrence_materialises_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(1));
        $recurrence->isActive = false;
        \Zenstruck\Foundry\Persistence\save($recurrence);

        $this->createEntry($category, $recurrence, self::monthStart(-2), FinanceEntryStatus::Paid);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'end_date' => self::monthStart(4)->format('Y-m-d'),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertSame(0, $responseData['created_entry_count']);
        $this->assertSame(0, $responseData['updated_entry_count']);
        $this->assertSame(0, $responseData['removed_entry_count']);
        $this->assertSame(1, $responseData['entry_count']);
        $this->assertSame($this->gridDates(-2, -2), $this->remainingEntryDates($recurrenceId));
    }

    /**
     * Extending and restarting in one call used to materialise twice: each pass asked the database
     * which dates were already taken without seeing the other's pending inserts, so every occurrence
     * the two ranges shared was created a second time.
     */
    public function test_extending_and_restarting_in_one_call_does_not_duplicate(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(1));
        $recurrence->isActive = false;
        \Zenstruck\Foundry\Persistence\save($recurrence);

        $this->createEntry($category, $recurrence, self::monthStart(-2), FinanceEntryStatus::Paid);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'is_active' => true,
            'end_date' => self::monthStart(3)->format('Y-m-d'),
        ]);

        $this->assertResponseIsSuccessful();

        // The paid entry it already had, then one per month still ahead. The months between the start
        // and today are not backfilled: restarting a recurrence forecasts, it does not rewrite history.
        $dates = $this->remainingEntryDates($recurrenceId);
        $this->assertSame($dates, array_values(array_unique($dates)), 'no date is materialised twice');
        $this->assertSame(
            [self::monthStart(-2)->format('Y-m-d'), ...$this->gridDates(1, 3)],
            $dates,
        );
    }

    public function test_changing_the_start_date_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'start_date' => self::monthStart(-1)->format('Y-m-d'),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'La date de début d\'une récurrence ne peut plus être modifiée. Terminez celle-ci et créez une nouvelle récurrence.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'La date de début d\'une récurrence ne peut plus être modifiée. Terminez celle-ci et créez une nouvelle récurrence.',
        ]);

        $this->assertSame(self::monthStart(-3)->format('Y-m-d'), $this->reloadRecurrence($recurrenceId)->startDate->format('Y-m-d'));
    }

    public function test_resending_the_unchanged_start_date_is_accepted(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));
        $this->createEntry($category, $recurrence, self::monthStart(2), FinanceEntryStatus::Planned);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'start_date' => self::monthStart(-3)->format('Y-m-d'),
            'label' => 'Loyer studio',
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => 'Loyer studio',
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-3)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 1,
            'updated_entry_count' => 1,
            'removed_entry_count' => 0,
            'created_entry_count' => 0,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);
    }

    public function test_changing_the_interval_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));

        $this->patchRecurrence($user, $bandSpace, (string) $recurrence->id, ['interval' => 'weekly']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'L\'intervalle d\'une récurrence ne peut plus être modifié. Terminez celle-ci et créez une nouvelle récurrence.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'L\'intervalle d\'une récurrence ne peut plus être modifié. Terminez celle-ci et créez une nouvelle récurrence.',
        ]);
    }

    public function test_extending_the_end_date_materialises_the_missing_forecasts(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(1));

        foreach ([-2, -1, 0, 1] as $offset) {
            $this->createEntry($category, $recurrence, self::monthStart($offset), FinanceEntryStatus::Planned);
        }

        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'end_date' => self::monthStart(3)->format('Y-m-d'),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-2)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 6,
            'updated_entry_count' => 0,
            'removed_entry_count' => 0,
            'created_entry_count' => 2,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        $this->assertSame($this->gridDates(-2, 3), $this->remainingEntryDates($recurrenceId));
    }

    public function test_shrinking_the_end_date_drops_the_forecasts_past_it_but_never_a_paid_one(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(3));

        foreach ([-2, -1, 0, 1, 2] as $offset) {
            $this->createEntry($category, $recurrence, self::monthStart($offset), FinanceEntryStatus::Planned);
        }
        $paidPastTheNewEnd = $this->createEntry($category, $recurrence, self::monthStart(3), FinanceEntryStatus::Paid);

        $recurrenceId = (string) $recurrence->id;
        $paidId = (string) $paidPastTheNewEnd->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, [
            'end_date' => self::monthStart(1)->format('Y-m-d'),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-2)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(1)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 5,
            'updated_entry_count' => 0,
            'removed_entry_count' => 1,
            'created_entry_count' => 0,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        $remainingIds = $this->remainingEntryIds($recurrenceId);
        $this->assertCount(5, $remainingIds);
        $this->assertContains($paidId, $remainingIds, 'A paid occurrence survives its recurrence being cut short');
    }

    public function test_an_edit_that_changes_nothing_leaves_the_entries_alone(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3));

        $first = $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Planned);
        $second = $this->createEntry($category, $recurrence, self::monthStart(2), FinanceEntryStatus::Planned);

        $recurrenceId = (string) $recurrence->id;
        $entryIds = ['first' => (string) $first->id, 'second' => (string) $second->id];

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['label' => self::LABEL, 'amount' => 30000]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId);
        $this->assertSame([], $activities, 'Nothing changed, so nothing is worth an activity entry');

        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceRecurrence',
            '@id' => $this->recurrenceIri($bandSpace, $recurrenceId),
            '@type' => 'FinanceRecurrence',
            'id' => $recurrenceId,
            'band_space_id' => (string) $bandSpace->id,
            'category_id' => (string) $category->id,
            'category_name' => 'Studio',
            'label' => self::LABEL,
            'type' => 'expense',
            'amount' => 30000,
            'scope' => 'band',
            'interval' => 'monthly',
            'start_date' => self::monthStart(-1)->format(\DateTimeInterface::ATOM),
            'end_date' => self::monthStart(3)->format(\DateTimeInterface::ATOM),
            'is_active' => true,
            'entry_count' => 2,
            'updated_entry_count' => 0,
            'removed_entry_count' => 0,
            'created_entry_count' => 0,
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'update_datetime' => $responseData['update_datetime'],
        ]);

        $entries = $this->reloadEntries($entryIds);
        $this->assertNull($entries['first']->updateDatetime);
        $this->assertNull($entries['second']->updateDatetime);
    }

    public function test_patching_the_end_date_beyond_the_three_year_cap_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->patchRecurrence($user, $bandSpace, (string) $recurrence->id, ['end_date' => '2028-01-01']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La durée maximale est de 3 ans',
                    'code' => RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
                ],
            ],
            'detail' => 'end_date: La durée maximale est de 3 ans',
            'description' => 'end_date: La durée maximale est de 3 ans',
            'type' => '/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_MAX_DURATION,
            'title' => 'An error occurred',
        ]);
    }

    public function test_patching_an_end_date_before_the_start_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, new \DateTime('2024-01-01'), new \DateTime('2024-12-31'));

        $this->patchRecurrence($user, $bandSpace, (string) $recurrence->id, ['end_date' => '2023-06-01']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'end_date',
                    'message' => 'La date de fin doit être postérieure à la date de début',
                    'code' => RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
                ],
            ],
            'detail' => 'end_date: La date de fin doit être postérieure à la date de début',
            'description' => 'end_date: La date de fin doit être postérieure à la date de début',
            'type' => '/validation_errors/' . RecurrenceEndDateValidator::ERROR_CODE_BEFORE_START,
            'title' => 'An error occurred',
        ]);
    }

    public function test_update_recurrence_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-3), self::monthStart(3));

        $this->patchRecurrence($otherUser, $bandSpace, (string) $recurrence->id, ['amount' => 35000]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * A personal recurrence answers only to the member its forecasts belong to. The entry endpoints
     * have always said so; the recurrence endpoints did not, so anybody in the band could reprice, or
     * shorten, somebody else's personal series.
     */
    public function test_updating_a_personal_recurrence_of_another_member_is_refused(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $intruder = UserFactory::new()->create(['username' => 'intruder', 'email' => 'intruder@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $intruder])->create();

        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3), FinanceEntryScope::Personal);
        $forecast = $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Planned, $ownerMembership);
        $recurrenceId = (string) $recurrence->id;
        $forecastId = (string) $forecast->id;

        $this->patchRecurrence($intruder, $bandSpace, $recurrenceId, ['amount' => 99000]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez modifier que vos propres récurrences personnelles',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous ne pouvez modifier que vos propres récurrences personnelles',
        ]);

        $this->assertSame(30000, $this->reloadRecurrence($recurrenceId)->amount);
        $this->assertSame(30000, self::getContainer()->get(FinanceEntryRepository::class)->find($forecastId)->amount);
    }

    /**
     * The documented hole in inferring ownership from the entries: a personal recurrence that never
     * planned anything, or whose entries were all deleted, records nobody, so there is nobody to
     * protect and the next caller becomes its owner. Reachable, because a member may delete every
     * Prévu and Engagé entry of their own series. Pinned so the behaviour is a decision rather than
     * something discovered later: closing it properly needs an owner column on the recurrence.
     */
    public function test_updating_a_personal_recurrence_that_records_no_owner_is_allowed(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $stranger])->create();

        $category = $this->createCategory($bandSpace);
        // Personal, but it has planned nothing, so no entry carries a member to read ownership from.
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3), FinanceEntryScope::Personal);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($stranger, $bandSpace, $recurrenceId, ['amount' => 42000]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(42000, $this->reloadRecurrence($recurrenceId)->amount);
    }

    /**
     * Entries can carry two different members, because a member may reassign their own personal entry
     * to somebody else. Ownership then reads as "either of them", so whichever acts next passes while
     * an unrelated member is still refused. Pinned because it is the ambiguity the inference cannot
     * resolve, and because the refusal is the half that must not regress.
     */
    public function test_a_personal_recurrence_recording_two_members_admits_both_and_refuses_a_third(): void
    {
        $first = UserFactory::new()->asBaseUser()->create();
        $second = UserFactory::new()->create(['username' => 'second_owner', 'email' => 'second@test.com']);
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $firstMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $first])->create();
        $secondMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $second])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $stranger])->create();

        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-2), self::monthStart(3), FinanceEntryScope::Personal);
        $this->createEntry($category, $recurrence, self::monthStart(-1), FinanceEntryStatus::Paid, $firstMembership);
        $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Planned, $secondMembership);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($stranger, $bandSpace, $recurrenceId, ['amount' => 99000]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame(30000, $this->reloadRecurrence($recurrenceId)->amount);
    }

    /** A band recurrence belongs to nobody in particular, so any member still edits it. */
    public function test_updating_a_band_recurrence_of_another_member_is_allowed(): void
    {
        $creator = UserFactory::new()->asBaseUser()->create();
        $otherMember = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $creatorMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $otherMember])->create();

        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3));
        // Its forecast carries a member, which on its own must not be read as ownership: only the
        // recurrence's own scope decides whether anybody owns the series.
        $this->createEntry($category, $recurrence, self::monthStart(1), FinanceEntryStatus::Planned, $creatorMembership);
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($otherMember, $bandSpace, $recurrenceId, ['amount' => 35000]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(35000, $this->reloadRecurrence($recurrenceId)->amount);
    }

    /**
     * Extending used to file the new occurrences under whoever pressed save, which split one personal
     * series across two owners and put half of it out of its owner's reach. The owner is now read from
     * the series itself.
     */
    public function test_extending_a_personal_recurrence_files_the_new_forecasts_under_its_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(1), FinanceEntryScope::Personal);
        foreach (range(-1, 1) as $offset) {
            $this->createEntry($category, $recurrence, self::monthStart($offset), FinanceEntryStatus::Planned, $ownerMembership);
        }
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($owner, $bandSpace, $recurrenceId, [
            'end_date' => self::monthStart(3)->format('Y-m-d'),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = $this->getResponseAsArray();
        $this->assertSame(2, $responseData['created_entry_count']);
        $this->assertSame($this->gridDates(-1, 3), $this->remainingEntryDates($recurrenceId));

        $ownerMembershipId = (string) $ownerMembership->id;
        foreach ($this->remainingEntries($recurrenceId) as $entry) {
            $this->assertSame(FinanceEntryScope::Personal, $entry->scope);
            $this->assertSame($ownerMembershipId, (string) $entry->member->id, $entry->date->format('Y-m-d'));
        }
    }

    /** The PATCH carried no constraint on the amount, so a recurrence could be repriced to a debt. */
    public function test_update_recurrence_with_a_negative_amount_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3));
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['amount' => -35000]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . GreaterThan::TOO_LOW_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'amount',
                    'message' => 'Le montant doit être positif',
                    'code' => GreaterThan::TOO_LOW_ERROR,
                ],
            ],
            'detail' => 'amount: Le montant doit être positif',
            'description' => 'amount: Le montant doit être positif',
            'type' => '/validation_errors/' . GreaterThan::TOO_LOW_ERROR,
            'title' => 'An error occurred',
        ]);

        $this->assertSame(30000, $this->reloadRecurrence($recurrenceId)->amount);
    }

    /** This one used to be a 500: the value went straight into FinanceEntryType::from(). */
    public function test_update_recurrence_with_an_unknown_type_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = $this->createCategory($bandSpace);
        $recurrence = $this->createRecurrence($category, self::monthStart(-1), self::monthStart(3));
        $recurrenceId = (string) $recurrence->id;

        $this->patchRecurrence($user, $bandSpace, $recurrenceId, ['type' => 'subvention']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'Type invalide',
                    'code' => Choice::NO_SUCH_CHOICE_ERROR,
                ],
            ],
            'detail' => 'type: Type invalide',
            'description' => 'type: Type invalide',
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    /**
     * The first of a month relative to today, so an occurrence never lands on the boundary between
     * "already due" and "still to come".
     */
    private static function monthStart(int $monthOffset): \DateTime
    {
        $date = new \DateTime('first day of this month');
        $date->setTime(0, 0);

        if ($monthOffset !== 0) {
            $date->modify(sprintf('%+d months', $monthOffset));
        }

        return $date;
    }

    /**
     * @return string[]
     */
    private function gridDates(int $firstOffset, int $lastOffset): array
    {
        return array_map(
            static fn (int $offset): string => self::monthStart($offset)->format('Y-m-d'),
            range($firstOffset, $lastOffset),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchRecurrence(User $user, BandSpace $bandSpace, string $recurrenceId, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->recurrenceIri($bandSpace, $recurrenceId),
            $payload,
            ['CONTENT_TYPE' => self::MERGE_PATCH_CONTENT_TYPE, 'HTTP_ACCEPT' => 'application/ld+json']
        );
    }

    private function recurrenceIri(BandSpace $bandSpace, string $recurrenceId): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId;
    }

    private function createCategory(BandSpace $bandSpace): FinanceCategory
    {
        return FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
    }

    private function createRecurrence(
        FinanceCategory $category,
        \DateTime $startDate,
        \DateTime $endDate,
        FinanceEntryScope $scope = FinanceEntryScope::Band,
    ): FinanceRecurrence {
        return FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => self::LABEL,
            'type' => FinanceEntryType::Expense,
            'scope' => $scope,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 30000,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
    }

    /** An entry with a member on it is a personal one: that member is what makes it personal. */
    private function createEntry(
        FinanceCategory $category,
        FinanceRecurrence $recurrence,
        \DateTime $date,
        FinanceEntryStatus $status,
        ?BandSpaceMembership $member = null,
    ): FinanceEntry {
        return FinanceEntryFactory::new([
            'category' => $category,
            'label' => self::LABEL,
            'type' => FinanceEntryType::Expense,
            'status' => $status,
            'scope' => $member instanceof BandSpaceMembership ? FinanceEntryScope::Personal : FinanceEntryScope::Band,
            'member' => $member,
            'amount' => 30000,
            'date' => $date,
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
    }

    /**
     * @param array<string, string> $entryIds
     * @return array<string, FinanceEntry>
     */
    private function reloadEntries(array $entryIds): array
    {
        $repository = self::getContainer()->get(FinanceEntryRepository::class);
        $this->clearEntityManager();

        return array_map(static fn (string $id): FinanceEntry => $repository->find($id), $entryIds);
    }

    private function reloadRecurrence(string $recurrenceId): FinanceRecurrence
    {
        $repository = self::getContainer()->get(FinanceRecurrenceRepository::class);
        $this->clearEntityManager();

        return $repository->find($recurrenceId);
    }

    /**
     * @return string[]
     */
    private function remainingEntryIds(string $recurrenceId): array
    {
        return array_map(
            static fn (FinanceEntry $entry): string => (string) $entry->id,
            $this->remainingEntries($recurrenceId),
        );
    }

    /**
     * @return string[]
     */
    private function remainingEntryDates(string $recurrenceId): array
    {
        return array_map(
            static fn (FinanceEntry $entry): string => $entry->date->format('Y-m-d'),
            $this->remainingEntries($recurrenceId),
        );
    }

    /**
     * @return FinanceEntry[]
     */
    private function remainingEntries(string $recurrenceId): array
    {
        $recurrence = $this->reloadRecurrence($recurrenceId);

        return self::getContainer()->get(FinanceEntryRepository::class)
            ->findBy(['recurrence' => $recurrence], ['date' => 'ASC']);
    }

    private function clearEntityManager(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }
}
