<?php

namespace App\Service\Finder\Musician\Formatter;

use App\Entity\Musician\MusicianAnnounce;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;

class PromptFormatter
{
    public function __construct(
        private readonly InstrumentRepository $instrumentRepository,
        private readonly StyleRepository      $styleRepository,
    ) {
    }

    public function formatSystemMessage(): array
    {
        $typeBand = MusicianAnnounce::TYPE_BAND;
        $typeMusician = MusicianAnnounce::TYPE_MUSICIAN;

        $instruments = $this->getInstrumentIds();
        $styles = $this->getStyleIds();

        return [
            "role"    => "system",
            "content" => <<<EOD
Voici le format du JSON requis en sortie: 
`{'type': $typeMusician|$typeBand, 'instrument': 'string', 'styles': 'array','latitude': 'float|null','longitude': 'float|null'}`
Voici les instruments disponibles (colonne 'instrument' dans le json) : $instruments.
Voici les styles disponibles (colonne style dans le json, plusieurs styles sont possible) : $styles.
Pour la colonne 'type' il y a 2 cas :
- si le user cherche un groupe alors la colonne 'type' = $typeMusician.
- si le user cherche un musicien (défini dans la colonne 'instrument') alors la colonne 'type' = $typeBand."
EOD
            ,
        ];
    }

    public function formatUserMessage(string $query): array
    {
        return [
            'role'    => 'user',
            'content' =>
                'Voici ma recherche : ' .
                $query
                . "\n"
                . "Le format JSON retourné doit être compréhensible par json_decode de PHP. Je n'ai besoin que du JSON, pas d'explications"
            ,
        ];
    }

    private function getInstrumentIds(): string
    {
        $instruments = $this->instrumentRepository->findAll();
        $arrayInstruments = [];
        foreach ($instruments as $instrument) {
            $arrayInstruments[] = $instrument->getSlug();
        }

        return json_encode($arrayInstruments);
    }

    private function getStyleIds(): string
    {
        $styles = $this->styleRepository->findAll();
        $arrayStyles = [];
        foreach ($styles as $style) {
            $arrayStyles[] = $style->getSlug();
        }

        return json_encode($arrayStyles);
    }
}