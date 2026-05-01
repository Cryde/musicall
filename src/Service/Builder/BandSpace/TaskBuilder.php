<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\Task;
use App\Entity\User;

readonly class TaskBuilder
{
    /**
     * @param Task[] $entities
     * @return TaskResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(Task $entity): TaskResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(Task $entity): TaskResource
    {
        $dto = new TaskResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->bandSpace->id;
        $dto->title = $entity->title;
        $dto->description = $entity->description;
        $dto->status = $entity->status->value;
        $dto->priority = $entity->priority->value;
        $dto->dueDate = $entity->dueDate?->format('Y-m-d');
        $dto->createdById = $entity->createdBy !== null ? (string) $entity->createdBy->id : null;
        $dto->createdByUsername = $entity->createdBy?->username;
        $dto->categoryId = $entity->category !== null ? (string) $entity->category->id : null;
        $dto->categoryName = $entity->category?->name;
        $dto->assignees = $entity->assignees->map(
            fn(User $user): array => [
                'id' => (string) $user->id,
                'username' => $user->username,
            ]
        )->toArray();
        $dto->assignees = array_values($dto->assignees);
        $dto->archiveDatetime = $entity->archiveDatetime?->format(\DateTimeInterface::ATOM);
        $dto->position = $entity->position;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
