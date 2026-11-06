<?php

declare(strict_types=1);

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\MusicianProfileBand;
use App\Repository\Attribute\StyleRepository;
use App\Translation\ReferenceNameTranslator;

readonly class MusicianBandResourceBuilder
{
    public function __construct(
        private StyleRepository $styleRepository,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    /**
     * @param array<array{name: string, style_id: string|null, role: string|null, current: bool, url: string|null}> $storedBands
     *
     * @return list<MusicianProfileBand>
     */
    public function buildList(array $storedBands): array
    {
        $styleNames = $this->styleNames(array_values(array_filter(array_column($storedBands, 'style_id'))));

        $list = [];
        foreach ($storedBands as $stored) {
            $band = new MusicianProfileBand();
            $band->name = $stored['name'];
            // A style removed since the band was saved simply shows no style.
            $styleName = $styleNames[$stored['style_id'] ?? ''] ?? null;
            $band->styleId = $styleName === null ? null : $stored['style_id'];
            $band->styleName = $styleName;
            $band->role = $stored['role'];
            $band->current = $stored['current'];
            $band->url = $stored['url'];
            $list[] = $band;
        }

        return $list;
    }

    /**
     * @param list<string> $styleIds
     *
     * @return array<string, string>
     */
    private function styleNames(array $styleIds): array
    {
        if ($styleIds === []) {
            return [];
        }
        $names = [];
        foreach ($this->styleRepository->findBy(['id' => $styleIds]) as $style) {
            $names[(string) $style->id] = $this->referenceNames->style($style);
        }

        return $names;
    }
}
