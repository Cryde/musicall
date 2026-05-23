<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Repository\BandSpace\SetlistRepository;
use DateTimeInterface;

readonly class SetlistBuilder
{
    public function __construct(
        private SetlistItemBuilder $itemBuilder,
        private SetlistRepository $setlistRepository,
    ) {
    }

    /**
     * @param Setlist[] $entities
     * @return SetlistResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (Setlist $entity): SetlistResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(Setlist $entity): SetlistResource
    {
        $dto = new SetlistResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->archiveDatetime = $entity->archiveDatetime?->format(DateTimeInterface::ATOM);
        $dto->creationDatetime = $entity->creationDatetime->format(DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(DateTimeInterface::ATOM);

        $items = $entity->items->toArray();
        usort(
            $items,
            fn (SetlistItem $a, SetlistItem $b): int => $a->position <=> $b->position,
        );
        $dto->items = array_map(
            fn (SetlistItem $item) => $this->itemBuilder->buildItem($item),
            $items,
        );

        $dto->totalDurationSeconds = $this->setlistRepository->totalDurationSeconds($entity);

        return $dto;
    }
}
