<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

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
use App\Enum\BandSpace\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FinanceRecurrenceDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_recurrence(): void
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

        $recurrence = FinanceRecurrenceFactory::new([
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

        // Create planned entries linked to the recurrence
        $plannedEntry1 = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Loyer salle',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-01-01'),
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $plannedEntry2 = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Loyer salle',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-02-01'),
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        // Create a paid entry linked to the recurrence (should survive deletion)
        $paidEntry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Loyer salle',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'scope' => FinanceEntryScope::Band,
            'amount' => 50000,
            'date' => new \DateTime('2024-03-01'),
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $recurrence = $recurrence->_real();
        $recurrenceId = (string) $recurrence->id;
        $paidEntryId = (string) $paidEntry->_real()->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        // Recurrence should be deleted
        $recurrenceRepository = self::getContainer()->get(FinanceRecurrenceRepository::class);
        $this->assertNull($recurrenceRepository->find($recurrenceId));

        // Planned entries should be deleted
        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        $remainingEntries = $entryRepository->findByBandSpace($bandSpace);
        $this->assertCount(1, $remainingEntries);

        // Paid entry should still exist with recurrence_id = null
        $paidEntryAfter = $entryRepository->find($paidEntryId);
        $this->assertNotNull($paidEntryAfter);
        $this->assertNull($paidEntryAfter->recurrence);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId);
        $this->assertCount(1, $activities);
        $this->assertSame('recurrence_deleted', $activities[0]->type);
        $this->assertSame(['label' => 'Loyer salle'], $activities[0]->payload);
    }

    public function test_delete_recurrence_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrence = FinanceRecurrenceFactory::new([
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

        $otherUser = $otherUser->_real();
        $bandSpace = $bandSpace->_real();
        $recurrence = $recurrence->_real();

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_recurrence_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrence = FinanceRecurrenceFactory::new([
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

        $user = $user->_real();
        $bandSpace = $bandSpace->_real();
        $recurrence = $recurrence->_real();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
