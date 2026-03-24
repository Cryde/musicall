<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\BandSpace\FinanceCategory;

readonly class FinanceCategoryBuilder
{
    /**
     * @param FinanceCategory[] $entities
     * @return FinanceCategoryResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn (FinanceCategory $entity): FinanceCategoryResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(FinanceCategory $entity): FinanceCategoryResource
    {
        $dto = new FinanceCategoryResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->parentId = $entity->parent !== null ? (string) $entity->parent->id : null;
        $dto->position = $entity->position;
        $dto->hasChildren = !$entity->children->isEmpty();
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
