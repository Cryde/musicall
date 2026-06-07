<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\BandSpace\InvitationStatus;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceInvitationRespondedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the inviter when their band space invitation is accepted or declined (#728), the mirror of
 * the "invitation sent" producer (#714). Best-effort per the epic #689 resilience contract: the event
 * is dispatched after the response is committed, and this listener swallows + logs any failure so it
 * can never roll back or 500 the accept/decline. The responder is excluded defensively - an inviter
 * cannot invite themselves, so inviter != responder by construction.
 */
#[AsEventListener]
readonly class BandSpaceInvitationRespondedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceInvitationRespondedEvent $event): void
    {
        $type = match ($event->outcome) {
            InvitationStatus::Accepted => NotificationType::BandSpaceInvitationAccepted,
            InvitationStatus::Declined => NotificationType::BandSpaceInvitationDeclined,
            default => null,
        };
        if ($type === null) {
            return;
        }

        $invitation = $event->invitation;
        $inviter = $invitation->invitedBy;
        if ((string) $inviter->id === (string) $event->responder->id) {
            return;
        }

        try {
            $this->notificationCreator->create($inviter, $type, [
                'band_space_id' => (string) $invitation->bandSpace->id,
                'band_space_name' => $invitation->bandSpace->name,
                'actor_id' => (string) $event->responder->id,
                'actor_username' => $event->responder->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space invitation response notification', [
                'invitation_id' => (string) $invitation->id,
                'exception' => $e,
            ]);
        }
    }
}
