<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Repository\BandSpace\TaskRepository;

/**
 * Takes a member who is on their way out off every task of the space they were assigned to, whether
 * they left, were removed by an admin or deleted their MusicAll account.
 *
 * An assignment is a statement about who is on the hook now, not a record of who did what: somebody
 * who is no longer in the band is on the hook for nothing, so the assignment goes everywhere,
 * archived and done tasks included. What actually happened is kept: the comments they wrote, the
 * tasks they created and the authorship of both stay untouched, and each revoked assignment is
 * written to the task activity feed as an `assignee_removed` entry, exactly like an admin clearing
 * the assignee by hand. That entry names the member and the task, so the band keeps the fact that
 * they were on it and can see when it stopped.
 *
 * A task left with nobody on it is not reassigned and nobody is notified about it. Picking a new
 * owner is the band's call, not the app's, and one bell per orphaned task would turn removing a busy
 * member into a burst of notifications. The work stays findable instead: the activity feed carries
 * every revocation, and the board has a « Non assigné » filter for the tasks that came out of it
 * with no one on them.
 *
 * Shared by the three departure paths so a member who disappears one way does not keep their
 * assignments while a member who disappears another way loses them.
 */
readonly class TaskAssignmentRevoker
{
    public function __construct(
        private TaskRepository $taskRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    /**
     * Mutates the assignee collections without flushing, so the caller commits the whole departure
     * at once.
     */
    public function revokeForMember(BandSpaceMembership $membership, User $actor): void
    {
        $departingUser = $membership->user;
        $departingUserId = (string) $departingUser->id;

        foreach ($this->taskRepository->findByBandSpaceAndAssignee($membership->bandSpace, $departingUser) as $task) {
            // Matched by id rather than handed the user object, as TaskUpdateProcedure does when an
            // admin clears an assignee by hand: the removal must not hinge on the collection having
            // hydrated the very instance we are holding.
            foreach ($task->assignees as $assignee) {
                if ((string) $assignee->id === $departingUserId) {
                    $task->assignees->removeElement($assignee);

                    break;
                }
            }

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $membership->bandSpace,
                module: BandSpaceModule::Task,
                type: BandSpaceTaskActivityType::AssigneeRemoved,
                resourceId: $task->id,
                actor: $actor,
                payload: [
                    'assignee_id' => $departingUser->id,
                    'assignee_username' => $departingUser->username,
                ],
            );
        }
    }
}
