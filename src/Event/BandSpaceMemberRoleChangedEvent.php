<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceMemberRoleChangedEvent extends Event
{
    public function __construct(
        public readonly BandSpaceMembership $membership,
        public readonly Role $oldRole,
        public readonly User $actor,
    ) {
    }
}
