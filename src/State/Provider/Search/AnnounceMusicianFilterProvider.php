<?php

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\Finder\Musician\MusicianFilterGenerator;

readonly class AnnounceMusicianFilterProvider implements ProviderInterface
{
    public function __construct(
        private MusicianFilterGenerator $musicianFilterGenerator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$params = $operation->getParameters()) {
            return null;
        }
        $search = $params->get('search')?->getValue();

        return $this->musicianFilterGenerator->find($search);
    }
}
