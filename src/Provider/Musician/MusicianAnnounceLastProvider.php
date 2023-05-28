<?php

namespace App\Provider\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Musician\MusicianAnnounceRepository;

class MusicianAnnounceLastProvider implements ProviderInterface
{
    public function __construct(
        private readonly MusicianAnnounceRepository $musicianAnnounceRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->musicianAnnounceRepository->findBy([], ['creationDatetime' => 'DESC'], MusicianAnnounce::LIMIT_LAST_ANNOUNCES);
    }
}