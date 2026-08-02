<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;

readonly class TechRiderBuilder
{
    /**
     * The list view reuses buildItem() because a rider is currently name plus dates.
     * That stops being true once sections, patch rows and the stage plot land: the
     * collection must not embed them, or listing a space ships every rider's full
     * content to render a table of names. SetlistBuilder shows the trap, it embeds
     * every item and runs a duration query per setlist even for the collection.
     *
     * @param TechRider[] $entities
     * @return TechRiderResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (TechRider $entity): TechRiderResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(TechRider $entity): TechRiderResource
    {
        $dto = new TechRiderResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->createdByUsername = $entity->createdBy?->username;
        $dto->archiveDatetime = $entity->archiveDatetime;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }
}
