<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Message\Message;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A message exists. Dispatched once per message, always, unlike MessageSentEvent, which is dispatched
 * per recipient and only when the email throttle says somebody should be emailed.
 *
 * Listeners swallow their own failures. This is dispatched before the email events so a dead mailer
 * cannot take the live update with it, and a listener that throws here would invert that.
 */
class MessagePostedEvent extends Event
{
    public function __construct(
        public readonly Message $message,
    ) {
    }
}
