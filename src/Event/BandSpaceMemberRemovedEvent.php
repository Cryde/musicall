<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceMemberRemovedEvent extends Event
{
    public function __construct(
        public readonly BandSpaceMembership $membership,
        public readonly User $actor,
    ) {
    }
}
