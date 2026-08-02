<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Repository\BandSpace\TechRiderItemRepository;

readonly class TechRiderBuilder
{
    public function __construct(
        private TechRiderItemBuilder $itemBuilder,
        private TechRiderItemRepository $itemRepository,
    ) {
    }

    /**
     * The list view carries counts, never item content. Riders gain patch rows and a
     * stage plot next, and embedding all of it to render a dropdown of names is the trap
     * SetlistBuilder already fell into, where the collection ships every item plus a
     * duration query per setlist.
     *
     * One grouped count query for the whole page rather than one per rider.
     *
     * @param TechRider[] $entities
     * @return TechRiderResource[]
     */
    public function buildFromList(array $entities): array
    {
        $counts = $this->itemRepository->countByRiders(
            array_values(array_map(static fn (TechRider $entity): string => (string) $entity->id, $entities)),
        );

        return array_map(
            function (TechRider $entity) use ($counts): TechRiderResource {
                $dto = $this->buildSummary($entity);
                $dto->itemCount = $counts[(string) $entity->id] ?? 0;

                return $dto;
            },
            $entities,
        );
    }

    /** Full rider, items included. Used by the item operations. */
    public function buildItem(TechRider $entity): TechRiderResource
    {
        $dto = $this->buildSummary($entity);

        $items = $entity->items->toArray();
        usort(
            $items,
            static fn (TechRiderItem $a, TechRiderItem $b): int => $a->position <=> $b->position,
        );

        $dto->items = $this->itemBuilder->buildFromList($items);
        $dto->itemCount = count($items);

        return $dto;
    }

    private function buildSummary(TechRider $entity): TechRiderResource
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
