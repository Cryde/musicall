<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * « Enregistrer dans cette tonalité »: moves every chord and the song's key in one write, so the two
 * can never disagree.
 */
class SongLyricsTranspose
{
    #[Assert\NotNull(message: 'Veuillez indiquer de combien de demi-tons transposer')]
    #[Assert\Range(min: -11, max: 11, notInRangeMessage: 'La transposition doit être entre {{ min }} et {{ max }} demi-tons')]
    #[Assert\NotEqualTo(value: 0, message: 'La transposition doit être d\'au moins un demi-ton')]
    public ?int $semitones = null;

    /** Unconstrained on purpose: a missing one is SongWriteGuard's 428, as on PATCH lyrics. */
    public ?int $expectedLyricsVersion = null;
}
