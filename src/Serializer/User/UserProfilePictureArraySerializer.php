<?php

namespace App\Serializer\User;

use App\Entity\Image\UserProfilePicture;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePictureArraySerializer
{
    private UploaderHelper $uploaderHelper;
    private CacheManager $cacheManager;

    public function __construct(UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
    }

    public function toArray(UserProfilePicture $userProfilePicture)
    {
        $path = $this->uploaderHelper->asset($userProfilePicture, 'imageFile');

        return [
            'small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small'),
        ];
    }
}
