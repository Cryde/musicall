<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\Song\SongResource;
use App\Entity\BandSpace\Song;
use App\Repository\BandSpace\LyricsRepository;
use App\Repository\BandSpace\SetlistItemRepository;
use DateTimeInterface;

readonly class SongBuilder
{
    public function __construct(
        private SetlistItemRepository $setlistItemRepository,
        private LyricsRepository $lyricsRepository,
    ) {
    }

    /**
     * @param Song[] $entities
     * @return SongResource[]
     */
    public function buildFromList(array $entities): array
    {
        $songIds = array_values(array_map(static fn (Song $song): string => (string) $song->id, $entities));
        $setlistsBySong = $this->setlistItemRepository->findLiveSetlistsBySongIds($songIds);
        $withLyrics = $this->lyricsRepository->findSongIdsWithLyrics($songIds);

        return array_map(
            fn (Song $entity): SongResource => $this->build(
                $entity,
                $setlistsBySong[(string) $entity->id] ?? [],
                isset($withLyrics[(string) $entity->id]),
            ),
            $entities,
        );
    }

    public function buildItem(Song $entity): SongResource
    {
        $songId = (string) $entity->id;

        return $this->build(
            $entity,
            $this->setlistItemRepository->findLiveSetlistsBySongIds([$songId])[$songId] ?? [],
            $this->lyricsRepository->findSongIdsWithLyrics([$songId]) !== [],
        );
    }

    /**
     * @param list<array{id: string, name: string}> $setlists
     */
    private function build(Song $entity, array $setlists, bool $hasLyrics): SongResource
    {
        $dto = new SongResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->title = $entity->title;
        $dto->tempo = $entity->tempo;
        $dto->tonality = $entity->tonality;
        $dto->referenceDuration = $entity->referenceDuration;
        $dto->notes = $entity->notes;
        $dto->hasLyrics = $hasLyrics;
        $dto->setlists = $setlists;
        $dto->archiveDatetime = $entity->archiveDatetime;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }
}
