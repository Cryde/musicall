<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceDeletionStateChangedEvent extends Event
{
    /**
     * @param bool $scheduled true when the deletion was just scheduled, false when it was cancelled
     *                        (the discriminator the listener maps to a notification type)
     */
    public function __construct(
        public readonly BandSpace $bandSpace,
        public readonly User $actor,
        public readonly bool $scheduled,
    ) {
    }
}
