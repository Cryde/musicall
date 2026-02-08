<?php

declare(strict_types=1);

namespace App\Service\Builder\Forum;

use App\ApiResource\Forum\Data\User;
use App\Entity\User as UserEntity;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserDtoBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    public function buildFromEntity(UserEntity $user): User
    {
        $dto = new User();
        $dto->id = (string) $user->getId();
        $dto->username = $user->getUsername();
        $dto->deletionDatetime = $user->getDeletionDatetime();
        $dto->profilePicture = $this->buildProfilePicture($user);

        return $dto;
    }

    /**
     * @return array{small: string}|null
     */
    private function buildProfilePicture(UserEntity $user): ?array
    {
        $profilePicture = $user->getProfilePicture();
        if (!$profilePicture) {
            return null;
        }
        if (!$path = $this->uploaderHelper->asset($profilePicture, 'imageFile')) {
            return null;
        }

        return ['small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small')];
    }
}
