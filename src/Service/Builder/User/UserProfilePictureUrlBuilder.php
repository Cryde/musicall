<?php declare(strict_types=1);

namespace App\Service\Builder\User;

use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserProfilePictureUrlBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function build(User $user): ?string
    {
        $profilePicture = $user->profilePicture;
        if (!$profilePicture instanceof UserProfilePicture) {
            return null;
        }

        $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
    }

    /**
     * The same URL from a projected image name, for lists that deliberately do not hydrate a User.
     *
     * The asset path depends only on the image name, because the mapping uses a static directory
     * namer, so a transient instance resolves it without loading the owning entity or its user.
     */
    public function buildFromImageName(?string $imageName): ?string
    {
        if ($imageName === null) {
            return null;
        }

        $profilePicture = new UserProfilePicture();
        $profilePicture->imageName = $imageName;

        $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
    }
}
