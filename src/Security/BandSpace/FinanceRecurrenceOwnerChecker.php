<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\FinanceEntryRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A personal finance recurrence belongs to one member, and only that member may edit or delete it.
 *
 * The entry endpoints have always enforced this on the entries themselves. The recurrence endpoints did
 * not, so any member could delete somebody else's personal recurrence and take its planned entries with
 * it, which is precisely the operation the entry endpoints protect their owner from. Extending one was
 * as bad in a quieter way: the occurrences it generated were filed under whoever pressed Enregistrer,
 * splitting one series across two owners.
 *
 * FinanceRecurrence carries no owner column, so the owner is read from the entries the recurrence
 * planned, which RecurrenceEntryGenerator files under the creator's membership.
 */
readonly class FinanceRecurrenceOwnerChecker
{
    public function __construct(
        private FinanceEntryRepository $financeEntryRepository,
    ) {
    }

    /**
     * @return BandSpaceMembership the membership the recurrence plans its entries for, which is the
     *                             caller's own whenever the recurrence has an owner at all
     */
    public function checkCanUpdate(FinanceRecurrence $recurrence, BandSpaceMembership $membership): BandSpaceMembership
    {
        return $this->checkOwner($recurrence, $membership, 'Vous ne pouvez modifier que vos propres récurrences personnelles');
    }

    public function checkCanDelete(FinanceRecurrence $recurrence, BandSpaceMembership $membership): void
    {
        $this->checkOwner($recurrence, $membership, 'Vous ne pouvez supprimer que vos propres récurrences personnelles');
    }

    /**
     * Whether a member may read the recurrence at all. A personal one carries its own label and its
     * own amount, so it is as private as the entries it plans, and it was readable band wide.
     *
     * The same rule as the write checks deliberately, ownerless included: a recurrence nobody may be
     * identified with is one anybody may claim, and a read rule stricter than the write rule would
     * make it invisible to the very members allowed to edit it.
     */
    public function isVisibleTo(FinanceRecurrence $recurrence, BandSpaceMembership $membership): bool
    {
        if ($recurrence->scope !== FinanceEntryScope::Personal) {
            return true;
        }

        $ownerIds = $this->financeEntryRepository->findMemberIdsByRecurrence($recurrence);

        return $ownerIds === [] || in_array((string) $membership->id, $ownerIds, true);
    }

    private function checkOwner(FinanceRecurrence $recurrence, BandSpaceMembership $membership, string $message): BandSpaceMembership
    {
        if ($recurrence->scope !== FinanceEntryScope::Personal) {
            return $membership;
        }

        $ownerIds = $this->financeEntryRepository->findMemberIdsByRecurrence($recurrence);

        // A personal recurrence that planned nothing, or whose entries were all deleted, has nobody to
        // protect: the caller becomes its owner for whatever it materialises next.
        if ($ownerIds === [] || in_array((string) $membership->id, $ownerIds, true)) {
            return $membership;
        }

        throw new AccessDeniedHttpException($message);
    }
}
