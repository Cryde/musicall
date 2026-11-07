<?php

declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\PublicMusicianProfile;
use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Profile\PublicProfileGuard;
use App\Service\Builder\Musician\MusicianProfileBuilder;
use App\Service\Profile\ProfileViewTracker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicMusicianProfile>
 */
readonly class MusicianProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MusicianProfileBuilder $musicianProfileBuilder,
        private ProfileViewTracker $viewTracker,
        private PublicProfileGuard $publicProfileGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicMusicianProfile
    {
        if (!($user = $this->userRepository->findOneBy(['username' => $uriVariables['username'] ?? ''])) instanceof \App\Entity\User) {
            throw new NotFoundHttpException('profile.user_not_found');
        }

        if (!$user->isPubliclyVisible()) {
            throw new NotFoundHttpException('profile.user_not_found');
        }

        $this->publicProfileGuard->assertVisible($user);

        if (!($musicianProfile = $user->musicianProfile) instanceof \App\Entity\Musician\MusicianProfile) {
            throw new NotFoundHttpException('profile.musician.not_found');
        }

        $this->viewTracker->track($musicianProfile, $user);

        return $this->musicianProfileBuilder->build($musicianProfile);
    }
}
