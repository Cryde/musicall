<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Somebody was named with an `@` in a band's chat (#964).
 *
 * The band space travels with the message because a channel reaches it through its thread, and the
 * listener has no business knowing that route. Dispatched after the send has committed, per the #689
 * contract, and only when there is somebody to tell.
 */
class BandSpaceChatMentionedEvent extends Event
{
    /**
     * @param User[] $mentionedUsers active members named by the message, the author included:
     *                                excluding them is the listener's job, the same way
     *                                BandSpaceTaskMentionedEvent leaves it
     */
    public function __construct(
        public readonly Message $message,
        public readonly BandSpace $bandSpace,
        public readonly array $mentionedUsers,
    ) {
    }
}
