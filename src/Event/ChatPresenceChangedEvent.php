<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A member opened a conversation, a band's chat (#1040) or a direct one (#1246), or closed it. */
class ChatPresenceChangedEvent extends Event
{
    public function __construct(
        public readonly MessageThread $thread,
        public readonly User $member,
    ) {
    }
}
