<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Musician\MusicianAnnounce;
use Symfony\Contracts\EventDispatcher\Event;

class MusicianAnnouncePostedEvent extends Event
{
    public function __construct(
        public readonly MusicianAnnounce $announce,
    ) {
    }
}
