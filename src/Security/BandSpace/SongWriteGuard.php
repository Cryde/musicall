<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Song;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

readonly class SongWriteGuard
{
    public function assertWritable(Song $song): void
    {
        if ($song->archiveDatetime !== null) {
            throw new ConflictHttpException('band_space.song.archived_read_only');
        }
    }

    /**
     * Lyrics are minutes of work, so a save made from a copy someone else has since changed is
     * refused rather than allowed to wipe theirs (#1055), the rule notes follow.
     */
    public function assertLyricsVersion(Song $song, ?int $expectedLyricsVersion): void
    {
        if ($expectedLyricsVersion === null) {
            throw new PreconditionRequiredHttpException(
                'band_space.song.lyrics_version_required'
            );
        }

        if ($expectedLyricsVersion !== $song->lyricsVersion) {
            throw new ConflictHttpException(
                'band_space.song.lyrics_version_conflict'
            );
        }
    }
}
