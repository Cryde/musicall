<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\SetlistItemResource;
use App\ApiResource\BandSpace\Setlist\SetlistItemSongInfo;
use App\Entity\BandSpace\SetlistItem;
use App\Repository\BandSpace\LyricsRepository;
use DateTimeInterface;

readonly class SetlistItemBuilder
{
    public function __construct(
        private LyricsRepository $lyricsRepository,
    ) {
    }

    /**
     * One query for any number of items, so a list of setlists asks once rather than once per setlist.
     *
     * @param SetlistItem[] $items
     * @return array<string, true>
     */
    public function songIdsWithLyrics(array $items): array
    {
        return $this->lyricsRepository->findSongIdsWithLyrics(array_values(array_filter(array_map(
            static fn (SetlistItem $item): ?string => $item->song?->id,
            $items,
        ))));
    }

    /**
     * @param SetlistItem[] $entities
     * @param array<string, true> $songIdsWithLyrics from songIdsWithLyrics()
     * @return SetlistItemResource[]
     */
    public function buildFromList(array $entities, array $songIdsWithLyrics): array
    {
        return array_map(
            fn (SetlistItem $item): SetlistItemResource => $this->build($item, $songIdsWithLyrics),
            $entities,
        );
    }

    public function buildItem(SetlistItem $entity): SetlistItemResource
    {
        return $this->build($entity, $this->songIdsWithLyrics([$entity]));
    }

    /**
     * @param array<string, true> $songIdsWithLyrics
     */
    private function build(SetlistItem $entity, array $songIdsWithLyrics): SetlistItemResource
    {
        $dto = new SetlistItemResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->setlist->bandSpace->id;
        $dto->setlistId = (string) $entity->setlist->id;
        $dto->type = $entity->type;
        $dto->label = $entity->label;
        $dto->durationOverride = $entity->durationOverride;
        $dto->note = $entity->note;
        $dto->transition = $entity->transition;
        $dto->position = $entity->position;

        if ($entity->song !== null) {
            $songInfo = new SetlistItemSongInfo();
            $songInfo->id = (string) $entity->song->id;
            $songInfo->title = $entity->song->title;
            $songInfo->tempo = $entity->song->tempo;
            $songInfo->tonality = $entity->song->tonality;
            $songInfo->referenceDuration = $entity->song->referenceDuration;
            $songInfo->hasLyrics = isset($songIdsWithLyrics[$songInfo->id]);
            $songInfo->archiveDatetime = $entity->song->archiveDatetime;
            $dto->song = $songInfo;
        }

        return $dto;
    }
}
