<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderSectionResource;
use App\Entity\BandSpace\TechRiderSection;

readonly class TechRiderSectionBuilder
{
    /**
     * @param TechRiderSection[] $entities
     * @return TechRiderSectionResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (TechRiderSection $entity): TechRiderSectionResource => $this->buildItem($entity),
            $entities,
        );
    }

    public function buildItem(TechRiderSection $entity): TechRiderSectionResource
    {
        $dto = new TechRiderSectionResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->techRider->bandSpace->id;
        $dto->riderId = (string) $entity->techRider->id;
        $dto->title = $entity->title;
        $dto->content = $entity->content;
        $dto->position = $entity->position;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }
}
