<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song;

final readonly class SongSheetOptions
{
    public function __construct(
        public bool $showChords = true,
        public bool $showSingers = true,
        /** Semitones, applied to the chords and the key of the print only. */
        public int $transpose = 0,
    ) {
    }
}
