<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCategoryResource;
use App\Entity\BandSpace\TaskCategory;

readonly class TaskCategoryBuilder
{
    /**
     * @param TaskCategory[] $entities
     * @return TaskCategoryResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(TaskCategory $entity): TaskCategoryResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(TaskCategory $entity): TaskCategoryResource
    {
        $dto = new TaskCategoryResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->name = $entity->name;
        $dto->color = $entity->color;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
