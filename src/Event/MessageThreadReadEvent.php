<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A participant read past a message the other person wrote in a direct conversation, so that
 * person's « Vu » is out of date (#1243). Not dispatched for a re-read with nothing new.
 */
class MessageThreadReadEvent extends Event
{
    public function __construct(
        public readonly MessageThread $thread,
        public readonly User $reader,
    ) {
    }
}
