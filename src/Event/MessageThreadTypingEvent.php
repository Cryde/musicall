<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A participant is writing in a direct conversation (#1242). */
class MessageThreadTypingEvent extends Event
{
    public function __construct(
        public readonly MessageThread $thread,
        public readonly User $typist,
    ) {
    }
}
