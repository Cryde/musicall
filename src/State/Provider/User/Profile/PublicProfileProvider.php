<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Profile\PublicProfile;
use App\Repository\User\UserProfileRepository;
use App\Service\Builder\User\PublicProfileBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<PublicProfile>
 */
readonly class PublicProfileProvider implements ProviderInterface
{
    public function __construct(
        private UserProfileRepository $userProfileRepository,
        private PublicProfileBuilder $publicProfileBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PublicProfile
    {
        $username = $uriVariables['username'];
        $profile = $this->userProfileRepository->findByUsername($username);

        if (!$profile) {
            throw new NotFoundHttpException('Profil non trouvé');
        }

        if (!$profile->isPublic()) {
            throw new NotFoundHttpException('Ce profil est privé');
        }

        return $this->publicProfileBuilder->build($profile);
    }
}
