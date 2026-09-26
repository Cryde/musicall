<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A member opened the band's chat, or closed it (#1040). */
class BandSpaceChatPresenceChangedEvent extends Event
{
    public function __construct(
        public readonly BandSpace $bandSpace,
        public readonly MessageThread $channel,
        public readonly User $member,
    ) {
    }
}
