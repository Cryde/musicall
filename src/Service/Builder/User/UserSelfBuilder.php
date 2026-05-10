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
        $dto->id = $user->id;
        $dto->username = $user->username;
        $dto->email = $user->email;
        $dto->roles = $user->getRoles();
        $dto->profilePicture = $this->buildProfilePicture($user);
        $dto->usernameChangedDatetime = $user->usernameChangedDatetime;
        $dto->hasPassword = $user->password !== null;

        return $dto;
    }

    /**
     * @return array{small: string}|null
     */
    private function buildProfilePicture(User $user): ?array
    {
        $profilePicture = $user->profilePicture;
        if (!$profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            return null;
        }
        if (!$path = $this->uploaderHelper->asset($profilePicture, 'imageFile')) {
            return null;
        }

        return ['small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small')];
    }
}
