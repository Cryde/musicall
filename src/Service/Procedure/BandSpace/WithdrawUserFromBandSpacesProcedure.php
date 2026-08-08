<?php

declare(strict_types=1);

namespace App\Service\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Event\BandSpaceMemberRoleChangedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\BandSpaceDeletionScheduler;
use App\Service\BandSpace\PersonalRecurrenceDeactivator;
use App\Service\BandSpace\TaskAssignmentRevoker;
use DateTime;

/**
 * Takes a user out of every band space they still belong to, when they delete their MusicAll account.
 *
 * Deleting an account only anonymized the user row, so the membership stayed Active and, more often than
 * not, Admin. countAdmins() kept counting an account nobody can log into, which made BandSpaceAdminChecker
 * the door nobody could open: the band could no longer promote, invite, remove anyone or even schedule
 * the space for deletion. This procedure closes that hole, and every space it touches comes out with at
 * least one live admin or a deletion already scheduled.
 *
 * Three outcomes per space, decided on the state before the departure:
 *
 * - the leaver is the only active member: the space would be left with nobody, so it goes on the same
 *   30-day deletion clock an admin would have started by hand. Members can no longer restore it because
 *   there are no members, and app:band-space:purge removes the rows and the stored objects on the due date.
 * - the departure would leave the space without an active admin: the longest-standing remaining member is
 *   promoted. Longest-standing rather than, say, the most recently active, because join date is a fact the
 *   band can check and agree with, and the repository breaks ties on the id so the outcome is one and the
 *   same successor every time.
 * - anything else: the membership is simply marked as left, exactly as the Quitter button does.
 *
 * Runs after the user row has been anonymized on purpose. The activity payloads and the promotion
 * notification are written with the anonymized handle, so deleting an account does not scatter fresh
 * copies of the old username through other people's data.
 *
 * The promotion is the only thing worth a notification, because it is the only one with both a live
 * recipient and a consequence: the successor did not ask for the job and is now the only person who can
 * administer the space. BandSpaceMemberRemovedEvent is deliberately not dispatched, its recipient is the
 * departing member and that account is gone; neither is BandSpaceDeletionStateChangedEvent, since the
 * space only gets scheduled when nobody is left to read it. The departure itself lands in the settings
 * activity feed, which is where the band looks for who came and went.
 */
readonly class WithdrawUserFromBandSpacesProcedure
{
    public function __construct(
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceDeletionScheduler $bandSpaceDeletionScheduler,
        private PersonalRecurrenceDeactivator $personalRecurrenceDeactivator,
        private TaskAssignmentRevoker $taskAssignmentRevoker,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    /**
     * Mutates the memberships without flushing, so the caller commits the whole account deletion at once.
     *
     * @return list<BandSpaceMemberRoleChangedEvent> the promotions to announce, to be dispatched by the
     *                                               caller once the commit has gone through
     */
    public function process(User $user): array
    {
        $promotions = [];

        foreach ($this->bandSpaceMembershipRepository->findActiveByUser($user) as $membership) {
            $promotion = $this->withdrawFrom($membership, $user);

            if ($promotion instanceof BandSpaceMemberRoleChangedEvent) {
                $promotions[] = $promotion;
            }
        }

        return $promotions;
    }

    private function withdrawFrom(BandSpaceMembership $membership, User $user): ?BandSpaceMemberRoleChangedEvent
    {
        $bandSpace = $membership->bandSpace;

        // Counted before the membership is touched: nothing is flushed yet, so these still describe the
        // space as it stands with the leaver in it.
        $activeMemberCount = $this->bandSpaceMembershipRepository->countActiveMembers($bandSpace);
        $adminsLeftBehind = $this->bandSpaceMembershipRepository->countAdmins($bandSpace)
            - ($membership->role === Role::Admin ? 1 : 0);

        $membership->status = MembershipStatus::Left;
        $membership->leftDatetime = new DateTime();

        $this->personalRecurrenceDeactivator->deactivateForMember($membership, $user);
        $this->taskAssignmentRevoker->revokeForMember($membership, $user);

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::MemberLeft,
            resourceId: $user->id,
            actor: $user,
            payload: [
                'target_user_id' => $user->id,
                'target_username' => $user->username,
            ],
        );

        if ($activeMemberCount === 1) {
            // Nobody is left to be promoted and nobody is left to restore the space either, so it is put
            // on the deletion clock rather than kept alive with an empty roster. Guarded because an admin
            // may already have scheduled it and rescheduling would silently extend their grace period.
            if (!$bandSpace->isPendingDeletion()) {
                $this->bandSpaceDeletionScheduler->schedule($bandSpace, $user);
            }

            return null;
        }

        if ($adminsLeftBehind > 0) {
            return null;
        }

        return $this->promoteSuccessor($membership, $user);
    }

    private function promoteSuccessor(BandSpaceMembership $membership, User $user): ?BandSpaceMemberRoleChangedEvent
    {
        // Excluded by id rather than by status: the departure is not flushed yet, so the query would
        // otherwise hand the leaver back to itself as its own successor.
        $successor = $this->bandSpaceMembershipRepository->findLongestStandingActiveMemberExcept(
            $membership->bandSpace,
            $membership
        );

        if (!$successor instanceof BandSpaceMembership) {
            return null;
        }

        $previousRole = $successor->role;
        $successor->role = Role::Admin;

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $membership->bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::MemberRoleChanged,
            resourceId: $successor->user->id,
            actor: $user,
            payload: [
                'from' => $previousRole->value,
                'to' => Role::Admin->value,
                'target_user_id' => $successor->user->id,
                'target_username' => $successor->user->username,
            ],
        );

        // The successor has to be told: they are the only one who can now administer the space, and
        // nobody chose them. Reuses the role-change notification rather than inventing a type for it.
        return new BandSpaceMemberRoleChangedEvent($successor, $previousRole, $user);
    }
}
