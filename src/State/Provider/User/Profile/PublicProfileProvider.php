<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Profile\PublicProfile;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Repository\UserRepository;
use App\Service\Builder\User\PublicProfileBuilder;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicProfile>
 */
readonly class PublicProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private PublicProfileBuilder $publicProfileBuilder,
        private ViewProcedure $viewProcedure,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicProfile
    {
        $username = $uriVariables['username'];
        if (!$user = $this->userRepository->findOneBy(['username' => $username])) {
            throw new NotFoundHttpException('Profil non trouvé');
        }

        if ($user->isDeleted()) {
            throw new NotFoundHttpException('Profil non trouvé');
        }

        $profile = $user->getProfile();
        if (!$profile->isPublic()) {
            throw new NotFoundHttpException('Ce profil est privé');
        }

        $this->trackView($user, $profile);

        return $this->publicProfileBuilder->build($user);
    }

    private function trackView(User $profileOwner, UserProfile $profile): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();

        // Don't count own views
        if ($currentUser && $currentUser->getId() === $profileOwner->getId()) {
            return;
        }

        $this->viewProcedure->process($profile, $request, $currentUser);
    }
}
