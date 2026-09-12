<?php

declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;

/**
 * Who is in a thread, for the two questions the send path asks.
 *
 * A direct message answers both from its `MessageParticipant` rows. A Band Space channel has none:
 * its members are derived from `BandSpaceMembership`, which is what keeps membership from needing a
 * second source of truth to synchronise on join, leave, kick and role change (#959).
 */
readonly class ThreadMemberResolver
{
    public function __construct(
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    /**
     * Who the message is for: read-state rows, unread counts, and later the live signal.
     *
     * @return User[]
     */
    public function activeMembersOf(MessageThread $thread): array
    {
        $bandSpace = $thread->bandSpace;
        if (!$bandSpace instanceof BandSpace) {
            return $this->participantsOf($thread);
        }

        return $this->usersOf($this->bandSpaceMembershipRepository->findByBandSpace($bandSpace));
    }

    /**
     * Whose rows the transaction must write-lock, which is a superset of the above and deliberately so.
     *
     * `BandSpaceMemberChecker::checkMember()` hydrates every membership's user with no status filter,
     * and a hydrated User makes the flush emit a phantom `UPDATE fos_user SET id` (#985), taking an
     * exclusive lock. Locking only the active members would leave the flush to lock the rest in
     * UnitOfWork order rather than id order, which is the inversion #957 closed.
     *
     * @return User[]
     */
    public function usersToLockFor(MessageThread $thread): array
    {
        $bandSpace = $thread->bandSpace;
        if (!$bandSpace instanceof BandSpace) {
            return $this->participantsOf($thread);
        }

        return $this->usersOf(
            $this->bandSpaceMembershipRepository->findByBandSpace($bandSpace, includeInactive: true)
        );
    }

    /**
     * @return User[]
     */
    private function participantsOf(MessageThread $thread): array
    {
        $users = [];
        foreach ($thread->messageParticipants as $participant) {
            $users[] = $participant->participant;
        }

        return $users;
    }

    /**
     * De-duplicated by id: the same user twice would be looked up twice in findOrCreateMetaFor() and
     * become a `UNIQUE (thread_id, user_id)` violation on the second insert.
     *
     * @param BandSpaceMembership[] $memberships
     *
     * @return User[]
     */
    private function usersOf(array $memberships): array
    {
        $users = [];
        foreach ($memberships as $membership) {
            $users[(string) $membership->user->id] = $membership->user;
        }

        return array_values($users);
    }
}
