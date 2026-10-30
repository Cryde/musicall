<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsListItem;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsResource;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsSongInfo;
use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Service\BandSpace\Lyrics\LyricsSingerResolver;

readonly class LyricsBuilder
{
    public function __construct(
        private LyricsSingerResolver $singerResolver,
    ) {
    }

    public function buildItem(Lyrics $lyrics): LyricsResource
    {
        $dto = new LyricsResource();
        $dto->id = (string) $lyrics->id;
        $dto->bandSpaceId = (string) $lyrics->bandSpace->id;
        $dto->title = $lyrics->title;
        $dto->content = $lyrics->content;
        $dto->version = $lyrics->version;
        $dto->hasContent = $lyrics->content !== null;
        $dto->song = $this->songInfo($lyrics->song);
        $dto->singers = $this->singerResolver->singers((string) $lyrics->bandSpace->id, $lyrics->content);
        $dto->archiveDatetime = $lyrics->archiveDatetime;
        $dto->creationDatetime = $lyrics->creationDatetime;
        $dto->updateDatetime = $lyrics->updateDatetime;

        return $dto;
    }

    /**
     * @param Lyrics[] $entities
     * @return LyricsListItem[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(function (Lyrics $lyrics): LyricsListItem {
            $dto = new LyricsListItem();
            $dto->id = (string) $lyrics->id;
            $dto->bandSpaceId = (string) $lyrics->bandSpace->id;
            $dto->title = $lyrics->title;
            $dto->hasContent = $lyrics->content !== null;
            $dto->song = $this->songInfo($lyrics->song);
            $dto->archiveDatetime = $lyrics->archiveDatetime;
            $dto->creationDatetime = $lyrics->creationDatetime;
            $dto->updateDatetime = $lyrics->updateDatetime;

            return $dto;
        }, $entities);
    }

    private function songInfo(?Song $song): ?LyricsSongInfo
    {
        if (!$song instanceof Song) {
            return null;
        }

        $info = new LyricsSongInfo();
        $info->id = (string) $song->id;
        $info->title = $song->title;
        $info->tonality = $song->tonality;
        $info->archiveDatetime = $song->archiveDatetime;

        return $info;
    }
}
