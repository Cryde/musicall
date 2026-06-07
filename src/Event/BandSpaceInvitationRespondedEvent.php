<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\User;
use App\Enum\BandSpace\InvitationStatus;
use Symfony\Contracts\EventDispatcher\Event;

class BandSpaceInvitationRespondedEvent extends Event
{
    /**
     * @param InvitationStatus $outcome the response, always Accepted or Declined (the discriminator
     *                                  the listener maps to a notification type)
     */
    public function __construct(
        public readonly BandSpaceInvitation $invitation,
        public readonly User $responder,
        public readonly InvitationStatus $outcome,
    ) {
    }
}
