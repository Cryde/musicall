<?php

declare(strict_types=1);

namespace App\Model\Musician;

use App\Entity\Musician\MusicianAnnounce;

/** An announce that answers one of a member's announces (#1082), and why. */
final readonly class AnnounceMatch
{
    /**
     * @param list<string> $sharedStyles
     */
    public function __construct(
        public MusicianAnnounce $announce,
        public MusicianAnnounce $answered,
        public float $distanceMetres,
        public array $sharedStyles,
    ) {
    }

    /** More styles in common first, then the closest. */
    public function ranksBefore(self $other): bool
    {
        return count($this->sharedStyles) !== count($other->sharedStyles)
            ? count($this->sharedStyles) > count($other->sharedStyles)
            : $this->distanceMetres < $other->distanceMetres;
    }
}
