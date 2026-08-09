<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Song;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Refuses writes to an archived song.
 *
 * SongRepository::findOneByIdAndBandSpace() returns archived rows on purpose, because a setlist
 * item still has to render the song it was built with. The write side therefore states the rule
 * here, exactly as SetlistWriteGuard does one level up.
 *
 * Archiving is deliberately not guarded: it is how a song gets here, and the processor already
 * treats a second call as a no-op.
 */
readonly class SongWriteGuard
{
    public function assertWritable(Song $song): void
    {
        if ($song->archiveDatetime !== null) {
            throw new ConflictHttpException('Cette chanson est archivée, les modifications sont désactivées');
        }
    }
}
