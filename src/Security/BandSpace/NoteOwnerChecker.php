<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Deleting a note is owner or admin, the same rule the file and folder endpoints have always applied.
 *
 * It used to check membership alone, and a note deletion is a cascade: band_space_note.parent_id is
 * ON DELETE CASCADE, so any member could take a whole subtree of somebody else's pages behind a single
 * confirm dialog, with nothing but the activity feed left to say what had been there.
 *
 * An authorless note is admin only, exactly as an authorless file is. Both halves of that matter: a
 * note older than the createdBy column, or one whose author has closed their account, must not become
 * undeletable, and it must not become deletable by anyone who happens to be in the space either. That
 * is deliberately the opposite reading from FinanceRecurrenceOwnerChecker, which treats an ownerless
 * recurrence as claimable: a recurrence with no entries left protects nobody, whereas an old note is
 * still somebody's writing and the missing name is the only thing that is gone.
 */
readonly class NoteOwnerChecker
{
    public function checkCanDelete(BandSpaceNote $note, BandSpaceMembership $membership): void
    {
        if ($membership->role === Role::Admin) {
            return;
        }

        if ($note->createdBy instanceof User && $note->createdBy->id === $membership->user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer cette note');
    }
}
