<?php declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Musician\MusicianAnnounceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class MusicianAnnounceLastProvider implements ProviderInterface
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private MusicianAnnounceBuilder    $musicianAnnounceBuilder,
        private Security                   $security,
    ) {
    }

    /**
     * @return \App\ApiResource\Musician\MusicianAnnounce[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $type = $operation->getParameters()?->get('type')?->getValue();
        $viewer = $this->security->getUser();
        $entities = $this->musicianAnnounceRepository->findLastAnnounces(
            MusicianAnnounce::LIMIT_LAST_ANNOUNCES,
            is_string($type) && $type !== '' ? (int) $type : null,
            $viewer instanceof User ? $viewer : null,
        );
        $authorsByAnnounceId = $this->musicianAnnounceRepository->findAuthorsDataForAnnounces($entities);

        return $this->musicianAnnounceBuilder->buildListWithProjectedAuthors($entities, $authorsByAnnounceId);
    }
}
