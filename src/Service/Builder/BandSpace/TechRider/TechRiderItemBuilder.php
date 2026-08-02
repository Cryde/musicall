<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Entity\BandSpace\TechRiderItem;

readonly class TechRiderItemBuilder
{
    /**
     * @param TechRiderItem[] $entities
     * @return TechRiderItemResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (TechRiderItem $entity): TechRiderItemResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(TechRiderItem $entity): TechRiderItemResource
    {
        $dto = new TechRiderItemResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->techRider->bandSpace->id;
        $dto->riderId = (string) $entity->techRider->id;
        $dto->type = $entity->type->value;
        $dto->isIncluded = $entity->isIncluded;
        $dto->title = $entity->title;
        $dto->content = $entity->content;
        $dto->position = $entity->position;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }
}
