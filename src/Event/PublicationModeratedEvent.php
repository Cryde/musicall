<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Moderation\ModerationOutcome;
use Symfony\Contracts\EventDispatcher\Event;

class PublicationModeratedEvent extends Event
{
    public function __construct(
        public readonly Publication $publication,
        public readonly User $moderator,
        public readonly ModerationOutcome $outcome,
    ) {
    }
}
