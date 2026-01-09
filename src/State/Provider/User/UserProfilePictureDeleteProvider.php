<?php

declare(strict_types=1);

namespace App\State\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<UserProfilePicture>
 */
readonly class UserProfilePictureDeleteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserProfilePicture
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException();
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $profilePicture = $user->getProfilePicture();

        if ($profilePicture === null) {
            throw new NotFoundHttpException('Aucune photo de profil à supprimer');
        }

        return $profilePicture;
    }
}
