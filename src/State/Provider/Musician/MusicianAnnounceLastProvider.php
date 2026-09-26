<?php declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Musician\MusicianAnnounceBuilder;

/**
 * @implements ProviderInterface<object>
 */
readonly class MusicianAnnounceLastProvider implements ProviderInterface
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private MusicianAnnounceBuilder    $musicianAnnounceBuilder,
    ) {
    }

    /**
     * @return \App\ApiResource\Musician\MusicianAnnounce[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $type = $operation->getParameters()?->get('type')?->getValue();
        $entities = $this->musicianAnnounceRepository->findLastAnnounces(
            MusicianAnnounce::LIMIT_LAST_ANNOUNCES,
            is_string($type) && $type !== '' ? (int) $type : null,
        );
        $authorsByAnnounceId = $this->musicianAnnounceRepository->findAuthorsDataForAnnounces($entities);

        return $this->musicianAnnounceBuilder->buildListWithProjectedAuthors($entities, $authorsByAnnounceId);
    }
}
