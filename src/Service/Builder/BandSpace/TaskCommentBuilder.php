<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TaskCommentBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
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
        $dto->authorProfilePictureUrl = $this->buildProfilePictureUrl($entity->author);
        $dto->content = $entity->content;
        $dto->creationDatetime = $entity->creationDatetime->format(\DateTimeInterface::ATOM);

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
