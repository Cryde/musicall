<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpaceMembership;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A member quit a band space of their own accord.
 *
 * No separate actor, unlike {@see BandSpaceMemberRemovedEvent}: the member who left is the actor, so
 * the membership carries both. The membership is already flagged as left when this is dispatched.
 */
class BandSpaceMemberLeftEvent extends Event
{
    public function __construct(
        public readonly BandSpaceMembership $membership,
    ) {
    }
}
