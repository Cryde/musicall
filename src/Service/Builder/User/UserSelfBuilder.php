<?php

declare(strict_types=1);

namespace App\Service\Builder\User;

use App\ApiResource\User\UserSelf;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserSelfBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    public function buildFromEntity(User $user): UserSelf
    {
        $dto = new UserSelf();
        $dto->id = (string) $user->getId();
        $dto->username = $user->getUsername();
        $dto->email = $user->getEmail();
        $dto->roles = $user->getRoles();
        $dto->profilePicture = $this->buildProfilePicture($user);

        return $dto;
    }

    /**
     * @return array{small: string}|null
     */
    private function buildProfilePicture(User $user): ?array
    {
        $profilePicture = $user->getProfilePicture();
        if (!$profilePicture) {
            return null;
        }

        $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');

        return ['small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small')];
    }
}
