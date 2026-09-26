<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\Message;
use App\Enum\Message\MessageChange;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A message of a Band Space channel changed in place (#1056): a reaction added or taken back, an
 * edit, a delete, a pin or an unpin. Dispatched after the write, and only when something changed.
 */
class BandSpaceChatMessageChangedEvent extends Event
{
    public function __construct(
        public readonly Message $message,
        public readonly MessageChange $change,
    ) {
    }
}
