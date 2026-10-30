<?php declare(strict_types=1);

namespace App\Service\BandSpace\Lyrics;

use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Service\BandSpace\Song\ChordPro\ChordTransposer;
use DateTime;

/**
 * « Enregistrer dans cette tonalité » (#1055): the chords and the song's key move in one write, so
 * the two never disagree. A key the transposer cannot read (« ? », free text) is left as it was.
 */
readonly class LyricsTransposition
{
    public function __construct(
        private ChordTransposer $transposer,
    ) {
    }

    public function apply(?Lyrics $lyrics, ?Song $song, int $semitones): void
    {
        if ($lyrics?->content !== null) {
            $lyrics->content = $this->transposer->transposeSource($lyrics->content, $semitones, $song?->tonality);
            ++$lyrics->version;
            $lyrics->updateDatetime = new DateTime();
        }
        if ($song instanceof Song) {
            $song->tonality = $this->transposer->transposeKey($song->tonality, $semitones) ?? $song->tonality;
            $song->updateDatetime = new DateTime();
        }
    }
}
