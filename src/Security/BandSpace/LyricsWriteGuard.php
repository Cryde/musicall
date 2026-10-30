<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Lyrics;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

readonly class LyricsWriteGuard
{
    /** The revision of lyrics never written yet, the one a first save must name. */
    public const int FIRST_VERSION = 1;

    /**
     * Lyrics are minutes of work, so a save made from a copy someone else has since changed is
     * refused rather than allowed to wipe theirs (#1055), the rule notes follow.
     */
    public function assertVersion(?Lyrics $lyrics, ?int $expectedVersion): void
    {
        if ($expectedVersion === null) {
            throw new PreconditionRequiredHttpException('band_space.song.lyrics_version_required');
        }
        if ($expectedVersion !== ($lyrics->version ?? self::FIRST_VERSION)) {
            throw self::conflict();
        }
    }

    public static function conflict(): ConflictHttpException
    {
        return new ConflictHttpException('band_space.song.lyrics_version_conflict');
    }
}
