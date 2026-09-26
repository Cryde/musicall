<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/** A member is writing in the band's channel (#1040). */
class BandSpaceChatTypingEvent extends Event
{
    public function __construct(
        public readonly BandSpace $bandSpace,
        public readonly MessageThread $channel,
        public readonly User $typist,
    ) {
    }
}
