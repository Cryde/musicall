<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Profile\UserProfileEdit;
use App\Entity\User;
use App\Entity\User\UserProfile;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * @implements ProviderInterface<UserProfileEdit>
 */
readonly class UserProfileEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserProfileEdit
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();

        if (!$profile) {
            throw new NotFoundHttpException('Profil non trouvé');
        }

        return $this->buildFromEntity($profile);
    }

    private function buildFromEntity(UserProfile $profile): UserProfileEdit
    {
        $user = $profile->getUser();
        $dto = new UserProfileEdit();

        $dto->bio = $profile->getBio();
        $dto->location = $profile->getLocation();
        $dto->isPublic = $profile->isPublic();

        if ($user->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($user->getProfilePicture(), 'imageFile');
            $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
        }

        if ($profile->getCoverPicture()) {
            $path = $this->uploaderHelper->asset($profile->getCoverPicture(), 'imageFile');
            $dto->coverPictureUrl = $this->cacheManager->getBrowserPath($path, 'user_cover_picture');
        }

        return $dto;
    }
}
