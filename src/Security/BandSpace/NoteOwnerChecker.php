<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\BandSpaceNote;
use App\Enum\BandSpace\Role;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Deleting a note is owner or admin, the same rule the file and folder endpoints have always applied.
 *
 * It used to check membership alone, and a note deletion is a cascade: band_space_note.parent_id is
 * ON DELETE CASCADE, so any member could take a whole subtree of somebody else's pages behind a single
 * confirm dialog, with nothing but the activity feed left to say what had been there.
 *
 * There is no authorless case to handle: band_space_note.created_by_id is NOT NULL. The notes written
 * before the column existed were backfilled from their note_created activity, so "nobody recorded"
 * stopped being a state a note can be in. A closed account still compares by id, and cannot log in to
 * reach this check anyway.
 */
readonly class NoteOwnerChecker
{
    public function checkCanDelete(BandSpaceNote $note, BandSpaceMembership $membership): void
    {
        if ($membership->role === Role::Admin) {
            return;
        }

        if ($note->createdBy->id === $membership->user->id) {
            return;
        }

        throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer cette note');
    }
}
