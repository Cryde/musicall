<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\BandSpace\FinanceCategory;

readonly class FinanceCategoryBuilder
{
    /**
     * @param FinanceCategory[] $entities
     * @param array<string, int> $entryCounts entry count keyed by category id, absent meaning none
     * @return FinanceCategoryResource[]
     */
    public function buildFromList(array $entities, array $entryCounts = []): array
    {
        return array_map(
            fn (FinanceCategory $entity): FinanceCategoryResource => $this->buildItem(
                $entity,
                $entryCounts[(string) $entity->id] ?? 0
            ),
            $entities
        );
    }

    public function buildItem(FinanceCategory $entity, int $entryCount = 0): FinanceCategoryResource
    {
        $dto = new FinanceCategoryResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->parentId = $entity->parent instanceof \App\Entity\BandSpace\FinanceCategory ? (string) $entity->parent->id : null;
        $dto->position = $entity->position;
        $dto->hasChildren = !$entity->children->isEmpty();
        $dto->entryCount = $entryCount;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->updateDatetime = $entity->updateDatetime;

        return $dto;
    }
}
