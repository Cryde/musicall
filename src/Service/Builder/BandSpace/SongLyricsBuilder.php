<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Security\BandSpace\LyricsWriteGuard;
use App\Service\BandSpace\Lyrics\LyricsSingerResolver;

readonly class SongLyricsBuilder
{
    public function __construct(
        private LyricsSingerResolver $singerResolver,
    ) {
    }

    /** A song with no lyrics yet reads as empty lyrics at their first revision. */
    public function build(Song $song, ?Lyrics $lyrics): SongLyrics
    {
        $dto = new SongLyrics();
        $dto->id = (string) $song->id;
        $dto->bandSpaceId = (string) $song->bandSpace->id;
        $dto->lyrics = $lyrics?->content;
        $dto->lyricsVersion = $lyrics->version ?? LyricsWriteGuard::FIRST_VERSION;
        $dto->tonality = $song->tonality;
        $dto->singers = $this->singerResolver->singers((string) $song->bandSpace->id, $lyrics?->content);

        return $dto;
    }
}
