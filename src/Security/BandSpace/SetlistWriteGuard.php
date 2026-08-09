<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Setlist;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Refuses writes to an archived setlist.
 *
 * SetlistRepository::findOneByIdAndBandSpace() returns archived rows on purpose, because reads
 * legitimately need them: the PDF of last year's gig, the activity log, the file drawer. That makes
 * the finder unable to carry the rule, so every write path states it here instead. Without it a
 * stale tab or a bookmarked ?setlist=<id> renames the list, adds songs and reorders them, each
 * answered with a 200, into a row nobody can see and nothing can restore (no restore until #761).
 *
 * Deliberately not guarded: archiving, which is how a setlist gets here, and duplicating, which
 * reads the archived list without touching it and is the only way back out of the archive today.
 *
 * Same shape as BandSpaceWriteGuard and TechRiderWriteGuard.
 */
readonly class SetlistWriteGuard
{
    public function assertWritable(Setlist $setlist): void
    {
        if ($setlist->archiveDatetime !== null) {
            throw new ConflictHttpException('Cette setlist est archivée, les modifications sont désactivées');
        }
    }
}
