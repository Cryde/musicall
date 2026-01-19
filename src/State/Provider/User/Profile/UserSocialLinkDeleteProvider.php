<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Entity\User\UserSocialLink;
use App\Repository\User\UserSocialLinkRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<UserSocialLink>
 */
readonly class UserSocialLinkDeleteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserSocialLinkRepository $userSocialLinkRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserSocialLink
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();
        assert($profile !== null);

        $link = $this->userSocialLinkRepository->find($uriVariables['id']);

        if (!$link) {
            throw new NotFoundHttpException('Lien social non trouvé');
        }

        if ($link->getProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas supprimer ce lien');
        }

        return $link;
    }
}
