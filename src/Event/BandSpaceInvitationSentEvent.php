<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpaceInvitation;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceInvitationSentEvent extends Event
{
    public function __construct(public readonly BandSpaceInvitation $invitation)
    {
    }
}
