<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Procedure\User\DeleteAccountProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceRecurrenceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Deleting an account used to anonymize the user row and stop there, leaving an Active/Admin membership
 * behind that no living account could replace: the last-admin guard kept counting the dead account and
 * BandSpaceAdminChecker became a door nobody could open (#814).
 */
#[ResetDatabase]
class DeleteAccountBandSpaceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_sole_member_leaves_the_space_scheduled_for_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::Admin])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $userId = (string) $user->id;

        $this->deleteAccount($user);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertInstanceOf(BandSpace::class, $reloaded);
        $this->assertNotNull($reloaded->deletionScheduledDatetime);
        $this->assertSame(
            (new \DateTimeImmutable('+30 days'))->format('Y-m-d'),
            $reloaded->deletionScheduledDatetime->format('Y-m-d'),
        );

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertSame(0, $membershipRepository->countActiveMembers($reloaded));
        $this->assertSame(0, $membershipRepository->countAdmins($reloaded));

        $membership = $membershipRepository->findMembershipIncludingInactive($reloaded, $user);
        $this->assertNotNull($membership);
        $this->assertSame(MembershipStatus::Left, $membership->status);
        $this->assertNotNull($membership->leftDatetime);

        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        $scheduleActivities = $activityRepository->findForResource($reloaded, BandSpaceModule::Settings, $bandSpaceId);
        $this->assertCount(1, $scheduleActivities);
        $this->assertSame('deletion_scheduled', $scheduleActivities[0]->type);

        $departureActivities = $activityRepository->findForResource($reloaded, BandSpaceModule::Settings, $userId);
        $this->assertCount(1, $departureActivities);
        $this->assertSame('member_left', $departureActivities[0]->type);
        // The payload carries the anonymized handle: erasing an account must not scatter fresh copies of
        // the old username through the band's history.
        $this->assertSame([
            'target_user_id' => $userId,
            'target_username' => 'deleted_' . $userId,
        ], $departureActivities[0]->payload);
    }

    public function test_sole_member_does_not_reschedule_an_already_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $alreadyScheduledFor = new \DateTimeImmutable('+3 days');
        $bandSpace = BandSpaceFactory::new()->create([
            'name' => 'Mon groupe',
            'deletionScheduledDatetime' => $alreadyScheduledFor,
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'role' => Role::Admin])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->deleteAccount($user);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertInstanceOf(BandSpace::class, $reloaded);
        $this->assertNotNull($reloaded->deletionScheduledDatetime);
        $this->assertSame(
            $alreadyScheduledFor->format('Y-m-d'),
            $reloaded->deletionScheduledDatetime->format('Y-m-d'),
        );

        // Only the departure is logged, the space was already on the clock.
        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($reloaded, BandSpaceModule::Settings, $bandSpaceId);
        $this->assertCount(0, $activities);
    }

    public function test_last_admin_departure_promotes_the_longest_standing_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $earlyMember = UserFactory::new()->create(['username' => 'early_member', 'email' => 'early@test.com']);
        $lateMember = UserFactory::new()->create(['username' => 'late_member', 'email' => 'late@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $owner,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $earlyMember,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $lateMember,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-03-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->deleteAccount($owner);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertInstanceOf(BandSpace::class, $reloaded);
        // The band keeps running: it is not put on the deletion clock.
        $this->assertNull($reloaded->deletionScheduledDatetime);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertSame(2, $membershipRepository->countActiveMembers($reloaded));
        $this->assertSame(1, $membershipRepository->countAdmins($reloaded));

        $promoted = $membershipRepository->findMembership($reloaded, $earlyMember);
        $this->assertNotNull($promoted);
        $this->assertSame(Role::Admin, $promoted->role);

        $notPromoted = $membershipRepository->findMembership($reloaded, $lateMember);
        $this->assertNotNull($notPromoted);
        $this->assertSame(Role::User, $notPromoted->role);

        $ownerMembership = $membershipRepository->findMembershipIncludingInactive($reloaded, $owner);
        $this->assertNotNull($ownerMembership);
        $this->assertSame(MembershipStatus::Left, $ownerMembership->status);
    }

    public function test_promoted_successor_is_notified(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $successor = UserFactory::new()->create(['username' => 'successor', 'email' => 'successor@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $owner,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $successor,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $ownerId = (string) $owner->id;

        $this->deleteAccount($owner);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($successor, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceRoleChanged, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'band_space_name' => 'Mon groupe',
            'from' => 'user',
            'to' => 'admin',
            'actor_id' => $ownerId,
            'actor_username' => 'deleted_' . $ownerId,
        ], $notifications[0]->payload);

        // The deleted account is never notified about its own departure.
        $this->assertCount(0, $notificationRepository->findForRecipient($owner, 10, 0));
    }

    public function test_ordinary_member_departure_only_closes_their_membership(): void
    {
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $member = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $bandSpaceId = (string) $bandSpace->id;

        $this->deleteAccount($member);

        $reloaded = self::getContainer()->get(BandSpaceRepository::class)->findOneByIdWithMemberships($bandSpaceId);
        $this->assertInstanceOf(BandSpace::class, $reloaded);
        $this->assertNull($reloaded->deletionScheduledDatetime);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertSame(1, $membershipRepository->countActiveMembers($reloaded));
        $this->assertSame(1, $membershipRepository->countAdmins($reloaded));

        $adminMembership = $membershipRepository->findMembership($reloaded, $admin);
        $this->assertNotNull($adminMembership);
        $this->assertSame(Role::Admin, $adminMembership->role);

        $memberMembership = $membershipRepository->findMembershipIncludingInactive($reloaded, $member);
        $this->assertNotNull($memberMembership);
        $this->assertSame(MembershipStatus::Left, $memberMembership->status);

        // Nobody was promoted, so nobody is told anything.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_no_space_is_left_without_an_admin_or_a_deletion_scheduled(): void
    {
        $leaver = UserFactory::new()->asBaseUser()->create();
        $bandMate = UserFactory::new()->create(['username' => 'band_mate', 'email' => 'mate@test.com']);
        $otherAdmin = UserFactory::new()->create(['username' => 'other_admin', 'email' => 'other@test.com']);

        $soloSpace = BandSpaceFactory::new()->create(['name' => 'Projet solo']);
        BandSpaceMembershipFactory::new(['bandSpace' => $soloSpace, 'user' => $leaver, 'role' => Role::Admin])->create();

        $ledSpace = BandSpaceFactory::new()->create(['name' => 'Groupe dirigé']);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $ledSpace,
            'user' => $leaver,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $ledSpace,
            'user' => $bandMate,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();

        $guestSpace = BandSpaceFactory::new()->create(['name' => 'Groupe invité']);
        BandSpaceMembershipFactory::new(['bandSpace' => $guestSpace, 'user' => $otherAdmin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $guestSpace, 'user' => $leaver, 'role' => Role::User])->create();

        $spaceIds = [(string) $soloSpace->id, (string) $ledSpace->id, (string) $guestSpace->id];

        $this->deleteAccount($leaver);

        $bandSpaceRepository = self::getContainer()->get(BandSpaceRepository::class);
        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $pendingDeletionCount = 0;

        foreach ($spaceIds as $spaceId) {
            $reloaded = $bandSpaceRepository->findOneByIdWithMemberships($spaceId);
            $this->assertInstanceOf(BandSpace::class, $reloaded);

            if ($reloaded->isPendingDeletion()) {
                ++$pendingDeletionCount;

                continue;
            }

            $this->assertGreaterThan(
                0,
                $membershipRepository->countActiveMembers($reloaded),
                sprintf('%s has no active member left', $reloaded->name),
            );
            $this->assertGreaterThan(
                0,
                $membershipRepository->countAdmins($reloaded),
                sprintf('%s has no admin left', $reloaded->name),
            );
        }

        // Only the space nobody is left in goes on the clock, so the loop above is not passing vacuously.
        $this->assertSame(1, $pendingDeletionCount);

        // The deleted account belongs to nothing any more.
        $this->assertSame([], $membershipRepository->findActiveByUser($leaver));
    }

    public function test_promoted_successor_can_administer_the_space(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $successor = UserFactory::new()->create(['username' => 'successor', 'email' => 'successor@test.com']);
        $thirdMember = UserFactory::new()->create(['username' => 'third_member', 'email' => 'third@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $owner,
            'role' => Role::Admin,
            'creationDatetime' => new \DateTime('2024-01-01 10:00:00'),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $successor,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-02-01 10:00:00'),
        ])->create();
        $thirdMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $thirdMember,
            'role' => Role::User,
            'creationDatetime' => new \DateTime('2024-03-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $thirdMembershipId = (string) $thirdMembership->id;
        $thirdMemberId = (string) $thirdMember->id;

        // Deleted through the procedure rather than the endpoint: loginUser() only holds for a single
        // request, so spending it on the deletion would leave the assertion below unauthenticated.
        // The endpoint itself is covered by the other cases in this class.
        self::getContainer()->get(DeleteAccountProcedure::class)->process($owner);

        // The point of the fix: an admin action is possible again once the founder's account is gone.
        $this->client->loginUser($successor);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/members/' . $thirdMembershipId,
            ['role' => 'admin'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceMember',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/members/' . $thirdMembershipId,
            '@type' => 'BandSpaceMember',
            'id' => $thirdMembershipId,
            'band_space_id' => $bandSpaceId,
            'user_id' => $thirdMemberId,
            'username' => 'third_member',
            'role' => 'admin',
            'stage_name' => null,
            'display_name' => 'third_member',
            'instruments' => [],
            'profile_picture_url' => null,
            'creation_datetime' => '2024-03-01T10:00:00+00:00',
            'status' => 'active',
            'left_datetime' => null,
        ]);
    }

    public function test_personal_recurrences_of_the_deleted_member_are_stopped(): void
    {
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $member = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Mon groupe']);

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $member,
            'role' => Role::User,
        ])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Cotisations'])->create();
        $recurrence = FinanceRecurrenceFactory::new([
            'category' => $category,
            'label' => 'Cotisation mensuelle',
            'scope' => FinanceEntryScope::Personal,
            'isActive' => true,
        ])->create();

        FinanceEntryFactory::new([
            'category' => $category,
            'recurrence' => $recurrence,
            'member' => $memberMembership,
            'scope' => FinanceEntryScope::Personal,
            'status' => FinanceEntryStatus::Planned,
            'label' => 'past contribution',
            'date' => new \DateTime('-1 year'),
        ])->create();
        FinanceEntryFactory::new([
            'category' => $category,
            'recurrence' => $recurrence,
            'member' => $memberMembership,
            'scope' => FinanceEntryScope::Personal,
            'status' => FinanceEntryStatus::Planned,
            'label' => 'future contribution',
            'date' => new \DateTime('+1 year'),
        ])->create();

        $recurrenceId = (string) $recurrence->id;

        $this->deleteAccount($member);

        $reloadedRecurrence = self::getContainer()->get(FinanceRecurrenceRepository::class)->find($recurrenceId);
        $this->assertNotNull($reloadedRecurrence);
        $this->assertFalse($reloadedRecurrence->isActive);

        // The books keep what already happened, only what was planned ahead is dropped.
        $labels = array_map(
            static fn (FinanceEntry $entry): string => $entry->label,
            self::getContainer()->get(FinanceEntryRepository::class)->findByBandSpace($bandSpace),
        );
        $this->assertSame(['past contribution'], $labels);
    }

    private function deleteAccount(User $user): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/users/delete_account',
            ['password' => 'password'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
