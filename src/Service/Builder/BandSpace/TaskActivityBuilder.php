<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskActivityResource;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\Task;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class TaskActivityBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @param BandSpaceActivity[] $entities
     * @return TaskActivityResource[]
     */
    public function buildFromList(Task $task, array $entities): array
    {
        $result = [];
        foreach ($entities as $entity) {
            if ($entity->actor === null) {
                // Orphan rows (actor was deleted) are skipped to keep the wire shape stable.
                continue;
            }
            $result[] = $this->buildItem($task, $entity);
        }

        return $result;
    }

    public function buildItem(Task $task, BandSpaceActivity $entity): TaskActivityResource
    {
        if (!$entity->actor instanceof \App\Entity\User) {
            throw new \LogicException('Cannot build TaskActivityResource for a BandSpaceActivity with a null actor.');
        }

        $dto = new TaskActivityResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $task->bandSpace->id;
        $dto->taskId = (string) $task->id;
        $dto->actorId = (string) $entity->actor->id;
        $dto->actorUsername = $entity->actor->username;
        $dto->actorProfilePictureUrl = $this->profilePictureUrlBuilder->build($entity->actor);
        $dto->type = $entity->type;
        $dto->payload = $entity->payload;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
