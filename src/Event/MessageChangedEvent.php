<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\Message;
use App\Enum\Message\MessageChange;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A message of a direct conversation changed in place (#1245): a reaction added or taken back, an
 * edit, a delete. Dispatched after the write, and only when something changed. A band channel has
 * BandSpaceChatMessageChangedEvent.
 */
class MessageChangedEvent extends Event
{
    public function __construct(
        public readonly Message $message,
        public readonly MessageChange $change,
    ) {
    }
}
