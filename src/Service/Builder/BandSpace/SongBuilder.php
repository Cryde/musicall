<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\Song\SongResource;
use App\Entity\BandSpace\Song;
use DateTimeInterface;

readonly class SongBuilder
{
    /**
     * @param Song[] $entities
     * @return SongResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (Song $entity): SongResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(Song $entity): SongResource
    {
        $dto = new SongResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->title = $entity->title;
        $dto->tempo = $entity->tempo;
        $dto->tonality = $entity->tonality;
        $dto->referenceDuration = $entity->referenceDuration;
        $dto->notes = $entity->notes;
        $dto->archiveDatetime = $entity->archiveDatetime?->format(DateTimeInterface::ATOM);
        $dto->creationDatetime = $entity->creationDatetime->format(DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(DateTimeInterface::ATOM);

        return $dto;
    }
}
