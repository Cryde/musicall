<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Profile\PublicProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Profile\PublicProfileGuard;
use App\Service\Builder\User\PublicProfileBuilder;
use App\Service\Profile\ProfileViewTracker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicProfile>
 */
readonly class PublicProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private PublicProfileBuilder $publicProfileBuilder,
        private ProfileViewTracker $viewTracker,
        private PublicProfileGuard $publicProfileGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicProfile
    {
        $username = $uriVariables['username'];
        if (!($user = $this->userRepository->findOneBy(['username' => $username])) instanceof \App\Entity\User) {
            throw new NotFoundHttpException('profile.not_found');
        }

        if (!$user->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.not_found');
        }

        $this->publicProfileGuard->assertPublic($user);

        $this->viewTracker->track($user->profile, $user);

        return $this->publicProfileBuilder->build($user);
    }
}
