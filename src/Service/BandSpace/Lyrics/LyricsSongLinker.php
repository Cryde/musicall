<?php declare(strict_types=1);

namespace App\Service\BandSpace\Lyrics;

use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\SongWriteGuard;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Puts lyrics on a song of their Band Space, one text per song. Linking never overwrites: a song
 * that already has lyrics, or lyrics already on another song, has to be unlinked first.
 */
readonly class LyricsSongLinker
{
    public function __construct(
        private SongRepository $songRepository,
        private LyricsRepository $lyricsRepository,
        private SongWriteGuard $songWriteGuard,
    ) {
    }

    /**
     * @return bool false when the lyrics were already on that song
     */
    public function link(Lyrics $lyrics, string $songId): bool
    {
        $song = $this->songRepository->findOneByIdAndBandSpace($songId, $lyrics->bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('band_space.song.not_found');
        }
        if ($lyrics->song === $song) {
            return false;
        }
        if ($lyrics->song instanceof Song) {
            throw new ConflictHttpException('band_space.lyrics.already_linked');
        }
        $this->songWriteGuard->assertWritable($song);
        if ($this->lyricsRepository->findOneBySong($song) instanceof Lyrics) {
            throw self::songHasLyrics();
        }

        $lyrics->song = $song;

        return true;
    }

    /** Also the answer when another member's link to the same song lands first, on the unique index. */
    public static function songHasLyrics(): ConflictHttpException
    {
        return new ConflictHttpException('band_space.lyrics.song_has_lyrics');
    }
}
