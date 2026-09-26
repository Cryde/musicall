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
            throw new ConflictHttpException('Cette chanson est archivée, les modifications sont désactivées');
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
                'Indiquez la version des paroles sur laquelle vous avez travaillé pour les enregistrer.'
            );
        }

        if ($expectedLyricsVersion !== $song->lyricsVersion) {
            throw new ConflictHttpException(
                'Ces paroles ont été modifiées par un autre membre depuis que vous les avez ouvertes. Vos modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.'
            );
        }
    }
}
