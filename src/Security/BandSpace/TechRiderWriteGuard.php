<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\TechRider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Refuses writes to an archived rider.
 *
 * The interface already renders an archived rider read only, but that is a curtain: the
 * endpoints were open, so a stale tab or a direct call could still edit a document the band
 * considers filed away. Same shape as BandSpaceWriteGuard, which does this for a space
 * pending deletion.
 *
 * Archiving and unarchiving are deliberately not guarded: one is how a rider gets here and
 * the other is the way out.
 */
readonly class TechRiderWriteGuard
{
    public function assertWritable(TechRider $techRider): void
    {
        if ($techRider->isArchived()) {
            throw new ConflictHttpException('Ce tech rider est archivé, les modifications sont désactivées');
        }
    }
}
