<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpace;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * A space pending deletion is read only for the whole grace period: the window exists so members can
 * retrieve their files, so reads and downloads stay open while every write is rejected.
 *
 * Deliberately separate from the two checkers, which both delegate here, because
 * BandSpaceInvitationAcceptProcessor needs the same rule and has no checker to hang it on.
 */
readonly class BandSpaceWriteGuard
{
    public function assertWritable(BandSpace $bandSpace): void
    {
        if ($bandSpace->isPendingDeletion()) {
            throw new ConflictHttpException('Cet espace est en attente de suppression, les modifications sont désactivées');
        }
    }
}
