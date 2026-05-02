<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TaskBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    /**
     * @param Task[] $entities
     * @param array<string, int> $commentCounts  task id => comment count, missing keys default to 0
     * @return TaskResource[]
     */
    public function buildFromList(array $entities, array $commentCounts = []): array
    {
        return array_map(
            fn(Task $entity): TaskResource => $this->buildItem($entity, $commentCounts[(string) $entity->id] ?? 0),
            $entities
        );
    }

    public function buildItem(Task $entity, int $commentCount = 0): TaskResource
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
                'profile_picture_url' => $this->buildProfilePictureUrl($user),
            ]
        )->toArray();
        $dto->assignees = array_values($dto->assignees);
        $dto->archiveDatetime = $entity->archiveDatetime?->format(\DateTimeInterface::ATOM);
        $dto->completedDatetime = $entity->completedDatetime?->format(\DateTimeInterface::ATOM);
        $dto->position = $entity->position;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->updateDatetime = $entity->updateDatetime?->format(\DateTimeInterface::ATOM);
        $dto->commentCount = $commentCount;

        return $dto;
    }

    private function buildProfilePictureUrl(User $user): ?string
    {
        $profilePicture = $user->profilePicture;
        if (!$profilePicture) {
            return null;
        }

        $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
    }
}
