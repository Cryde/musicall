<?php

declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\MusicianProfileMedia;
use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Service\Builder\Musician\MusicianProfileMediaResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MusicianProfileMedia>
 */
readonly class MusicianProfileMediaProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private MusicianProfileMediaRepository $mediaRepository,
        private MusicianProfileMediaResourceBuilder $musicianProfileMediaResourceBuilder
    ) {
    }

    /**
     * @return MusicianProfileMedia[]|MusicianProfileMedia|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|MusicianProfileMedia|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        $profile = $user?->getMusicianProfile();

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($profile);
        }

        return $this->provideItem($uriVariables['id'], $profile);
    }

    /**
     * @return MusicianProfileMedia[]
     */
    private function provideCollection(?MusicianProfile $profile): array
    {
        if (!$profile) {
            return [];
        }

        $mediaList = $this->mediaRepository->findBy(
            ['musicianProfile' => $profile],
            ['position' => 'ASC']
        );

        return $this->musicianProfileMediaResourceBuilder->buildList($mediaList);
    }

    private function provideItem(string $id, ?MusicianProfile $profile): MusicianProfileMedia
    {
        if (!$profile) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }
        if (!$media = $this->mediaRepository->find($id)) {
            throw new NotFoundHttpException('Média non trouvé');
        }

        if ($media->getMusicianProfile()->getId() !== $profile->getId()) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas accéder à ce média');
        }

        return $this->musicianProfileMediaResourceBuilder->buildFromEntity($media);
    }
}
