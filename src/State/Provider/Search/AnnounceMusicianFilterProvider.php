<?php declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Service\Finder\Musician\MusicianFilterGenerator;

/**
 * @implements ProviderInterface<AnnounceMusicianFilter>
 */
readonly class AnnounceMusicianFilterProvider implements ProviderInterface
{
    public function __construct(
        private MusicianFilterGenerator $musicianFilterGenerator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AnnounceMusicianFilter
    {
        if (!$params = $operation->getParameters()) {
            return null;
        }
        $search = $params->get('search')?->getValue();

        return $this->musicianFilterGenerator->find($search);
    }
}
