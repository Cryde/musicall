<?php

namespace App\Service\Builder\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;

class MusicianAnnounceDirector
{
    public function __construct(private readonly InstrumentRepository $instrumentRepository, private readonly StyleRepository $styleRepository)
    {
    }

    public function createFromArray(array $data): MusicianAnnounce
    {
        $instrument = $this->instrumentRepository->find($data['instrument']);
        $announce = (new MusicianAnnounce())
            ->setType($data['type'])
            ->setLatitude($data['latitude'])
            ->setLongitude($data['longitude'])
            ->setLocationName($data['locationName'])
            ->setNote($data['note'])
            ->setInstrument($instrument)
        ;

        foreach ($this->styleRepository->findBy(['id' => $data['styles']]) as $style) {
            $announce->addStyle($style);
        }

        return $announce;
    }
}
