<?php

declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\PublicMusicianProfile;
use App\Repository\UserRepository;
use App\Service\Builder\Musician\MusicianProfileBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicMusicianProfile>
 */
readonly class MusicianProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private MusicianProfileBuilder $musicianProfileBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicMusicianProfile
    {
        if (!$user = $this->userRepository->findOneBy(['username' => $uriVariables['username'] ?? ''])) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        if (!$musicianProfile = $user->getMusicianProfile()) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        return $this->musicianProfileBuilder->build($musicianProfile);
    }
}
