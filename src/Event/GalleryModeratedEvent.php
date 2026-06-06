<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\Moderation\ModerationOutcome;
use Symfony\Contracts\EventDispatcher\Event;

class GalleryModeratedEvent extends Event
{
    public function __construct(
        public readonly Gallery $gallery,
        public readonly User $moderator,
        public readonly ModerationOutcome $outcome,
    ) {
    }
}
