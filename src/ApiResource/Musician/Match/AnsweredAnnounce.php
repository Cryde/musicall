<?php

declare(strict_types=1);

namespace App\ApiResource\Musician\Match;

/** The member's own announce a match answers, enough to name it on the card. */
class AnsweredAnnounce
{
    public string $id;
    public int $type;
    public string $instrumentName;
    public string $locationName;
}
