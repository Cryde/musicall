<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\BandSpace\TaskComment;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class TaskCommentBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

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
        $dto->authorProfilePictureUrl = $this->profilePictureUrlBuilder->build($entity->author);
        $dto->content = $entity->content;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
