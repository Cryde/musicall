<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class MessageSentEvent extends Event
{
    public function __construct(
        public readonly User $recipient,
        public readonly User $sender,
        public readonly MessageThread $thread,
    ) {
    }
}
