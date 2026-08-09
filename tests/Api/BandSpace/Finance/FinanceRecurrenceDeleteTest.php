<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceRecurrenceDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_recurrence(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

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
        FinanceEntryFactory::new([
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

        FinanceEntryFactory::new([
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
        $recurrenceId = (string) $recurrence->id;
        $paidEntryId = (string) $paidEntry->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);

        // Recurrence should be deleted
        $recurrenceRepository = self::getContainer()->get(FinanceRecurrenceRepository::class);
        $this->assertNull($recurrenceRepository->find($recurrenceId));

        // Planned entries should be deleted
        $entryRepository = self::getContainer()->get(FinanceEntryRepository::class);
        // Reloaded because the request detached it, and the finder binds it as a query parameter.
        $viewerMembership = self::getContainer()->get(BandSpaceMembershipRepository::class)->find((string) $viewerMembership->id);
        $remainingEntries = $entryRepository->findByBandSpace($bandSpace, $viewerMembership);
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
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

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

        $this->client->loginUser($otherUser);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_recurrence_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $viewerMembership = BandSpaceMembershipFactory::new([
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

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrence->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Deleting a recurrence hard-deletes every forecast it planned. A personal one belongs to the
     * member those forecasts are filed under, so this used to let any member destroy planned entries
     * they are explicitly forbidden from deleting one by one.
     */
    public function test_delete_personal_recurrence_of_another_member_is_refused(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $intruder = UserFactory::new()->create(['username' => 'intruder', 'email' => 'intruder@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $intruder])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $recurrence = FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Cordes',
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Personal,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 3000,
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-06-30'),
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $forecast = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Cordes',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Personal,
            'member' => $ownerMembership,
            'amount' => 3000,
            'date' => new \DateTime('2024-02-01'),
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrenceId = (string) $recurrence->id;
        $forecastId = (string) $forecast->id;

        $this->client->loginUser($intruder);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId);

        // 404 rather than the 403 this used to answer: somebody else's personal recurrence became
        // invisible on every operation, and a 403 would confirm it exists. The owner checks behind
        // this still refuse the write.
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Récurrence introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Récurrence introuvable',
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(FinanceRecurrenceRepository::class)->find($recurrenceId));
        $this->assertNotNull(self::getContainer()->get(FinanceEntryRepository::class)->find($forecastId));

        // The clear above detached it, and a detached entity cannot be bound as a query parameter.
        \Zenstruck\Foundry\Persistence\refresh($bandSpace);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $this->assertCount(0, $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $recurrenceId));
    }

    public function test_delete_own_personal_recurrence(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherMember = UserFactory::new()->create(['username' => 'other_member', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $viewerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $otherMember])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $recurrence = FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Cordes',
            'type' => FinanceEntryType::Expense,
            'scope' => FinanceEntryScope::Personal,
            'interval' => RecurrenceInterval::Monthly,
            'amount' => 3000,
            'startDate' => new \DateTime('2024-01-01'),
            'endDate' => new \DateTime('2024-06-30'),
            'isActive' => true,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $forecast = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Cordes',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Personal,
            'member' => $ownerMembership,
            'amount' => 3000,
            'date' => new \DateTime('2024-02-01'),
            'recurrence' => $recurrence,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();

        $recurrenceId = (string) $recurrence->id;
        $forecastId = (string) $forecast->id;

        $this->client->loginUser($owner);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/finance/recurrences/' . $recurrenceId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(FinanceRecurrenceRepository::class)->find($recurrenceId));
        $this->assertNull(self::getContainer()->get(FinanceEntryRepository::class)->find($forecastId));
    }
}
