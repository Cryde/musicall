<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\UserRegisteredEvent;
use App\Service\Procedure\BandSpace\AcceptPendingInvitationsProcedure;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class BandSpaceInvitationAutoAcceptListener
{
    public function __construct(
        private AcceptPendingInvitationsProcedure $acceptPendingInvitationsProcedure,
    ) {
    }

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->acceptPendingInvitationsProcedure->process($event->user);
    }
}
