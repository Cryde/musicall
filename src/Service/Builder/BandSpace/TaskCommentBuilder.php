<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\BandSpace\TaskComment;

readonly class TaskCommentBuilder
{
    /**
     * @param TaskComment[] $entities
     * @return TaskCommentResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(TaskComment $entity): TaskCommentResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(TaskComment $entity): TaskCommentResource
    {
        $dto = new TaskCommentResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->task->bandSpace->id;
        $dto->taskId = (string) $entity->task->id;
        $dto->authorId = (string) $entity->author->id;
        $dto->authorUsername = $entity->author->username;
        $dto->content = $entity->content;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
