<?php declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Musician\MusicianAnnounceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

readonly class MusicianAnnounceSelfProvider implements ProviderInterface
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private MusicianAnnounceBuilder    $musicianAnnounceBuilder,
        private Security                   $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $entities = $this->musicianAnnounceRepository->findBy(
            ['author' => $user],
            ['creationDatetime' => 'DESC']
        );

        return $this->musicianAnnounceBuilder->buildList($entities);
    }
}
