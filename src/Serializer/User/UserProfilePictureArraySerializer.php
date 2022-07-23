<?php

namespace App\Serializer\User;

use App\Entity\Image\UserProfilePicture;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePictureArraySerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper, private readonly CacheManager $cacheManager)
    {
    }

    public function toArray(UserProfilePicture $userProfilePicture): array
    {
        $path = $this->uploaderHelper->asset($userProfilePicture, 'imageFile');

        return [
            'small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small'),
        ];
    }
}
