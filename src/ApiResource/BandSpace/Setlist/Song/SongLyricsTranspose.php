<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * « Enregistrer dans cette tonalité »: moves every chord and the song's key in one write, so the two
 * can never disagree.
 */
class SongLyricsTranspose
{
    #[Assert\NotNull(message: 'band_space.song.transpose_required')]
    #[Assert\Range(min: -11, max: 11, notInRangeMessage: 'band_space.song.transpose_out_of_range')]
    #[Assert\NotEqualTo(value: 0, message: 'band_space.song.transpose_zero')]
    public ?int $semitones = null;

    /** Unconstrained on purpose: a missing one is SongWriteGuard's 428, as on PATCH lyrics. */
    public ?int $expectedLyricsVersion = null;
}
