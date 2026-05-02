<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Task\TaskActivityResource;
use App\Entity\BandSpace\TaskActivity;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class TaskActivityBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    /**
     * @param TaskActivity[] $entities
     * @return TaskActivityResource[]
     */
    public function buildFromList(array $entities): array
    {
        return array_map(
            fn(TaskActivity $entity): TaskActivityResource => $this->buildItem($entity),
            $entities
        );
    }

    public function buildItem(TaskActivity $entity): TaskActivityResource
    {
        $dto = new TaskActivityResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->task->bandSpace->id;
        $dto->taskId = (string) $entity->task->id;
        $dto->actorId = (string) $entity->actor->id;
        $dto->actorUsername = $entity->actor->username;
        $dto->actorProfilePictureUrl = $this->buildProfilePictureUrl($entity->actor);
        $dto->type = $entity->type;
        $dto->payload = $entity->payload;
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
