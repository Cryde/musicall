<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Image\UserProfileCoverPicture;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<UserProfileCoverPicture>
 */
readonly class UserProfileCoverPictureDeleteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserProfileCoverPicture
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();
        assert($profile !== null);

        $coverPicture = $profile->getCoverPicture();

        if ($coverPicture === null) {
            throw new NotFoundHttpException('Aucune photo de couverture à supprimer');
        }

        return $coverPicture;
    }
}
