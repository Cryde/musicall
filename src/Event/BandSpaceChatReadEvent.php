<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A member's read position in the space's channels moved past a message somebody else wrote, so
 * somebody's « Vu par » is now out of date (#977). Not dispatched for a re-read with nothing new.
 */
class BandSpaceChatReadEvent extends Event
{
    public function __construct(
        public readonly BandSpace $bandSpace,
        public readonly User $reader,
    ) {
    }
}
