<?php declare(strict_types=1);

namespace App\State\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Musician\MusicianAnnounce;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Musician\MusicianAnnounceBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class MusicianAnnounceItemProvider implements ProviderInterface
{
    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private MusicianAnnounceBuilder    $musicianAnnounceBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MusicianAnnounce
    {
        $entity = $this->musicianAnnounceRepository->find($uriVariables['id']);

        if (!$entity) {
            throw new NotFoundHttpException('Musician announce not found');
        }

        return $this->musicianAnnounceBuilder->buildItem($entity);
    }
}
