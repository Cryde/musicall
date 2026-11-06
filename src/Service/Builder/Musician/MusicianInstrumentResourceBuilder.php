<?php

declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\PublicMusicianProfileInstrument;
use App\Entity\Musician\MusicianProfileInstrument;
use App\Translation\ReferenceNameTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

/** A musician's instrument and level as the public pages show it: the musician page and the teacher page. */
readonly class MusicianInstrumentResourceBuilder
{
    public function __construct(
        private TranslatorInterface $translator,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    /**
     * @param iterable<MusicianProfileInstrument> $instruments
     *
     * @return list<PublicMusicianProfileInstrument>
     */
    public function buildList(iterable $instruments): array
    {
        $list = [];
        foreach ($instruments as $instrument) {
            $dto = new PublicMusicianProfileInstrument();
            $dto->instrumentId = (string) $instrument->instrument->id;
            $dto->instrumentName = $this->referenceNames->instrumentMusicianName($instrument->instrument);
            $dto->skillLevel = $instrument->skillLevel->value;
            $dto->skillLevelLabel = $instrument->skillLevel->trans($this->translator);
            $list[] = $dto;
        }

        return $list;
    }
}
