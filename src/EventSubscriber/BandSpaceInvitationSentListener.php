<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceInvitationSentEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Creates the in-app notification for the invited user when a band space
 * invitation is sent (#714). Best-effort per the epic #689 resilience
 * contract: the event is dispatched after the invitation is committed, and
 * this listener swallows + logs any failure so it can never roll back or 500
 * the invite. Actor exclusion is structural - the inviter is always a member
 * and the invitee never is, so recipient != actor by construction.
 */
#[AsEventListener]
readonly class BandSpaceInvitationSentListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceInvitationSentEvent $event): void
    {
        $invitation = $event->invitation;
        $recipient = $invitation->existingUser;

        // New-email invites have no account to notify yet (the invitee is
        // auto-added on registration); only existing users get a notification.
        if (!$recipient instanceof User) {
            return;
        }

        try {
            $this->notificationCreator->create($recipient, NotificationType::BandSpaceInvitation, [
                'band_space_id' => (string) $invitation->bandSpace->id,
                'band_space_name' => $invitation->bandSpace->name,
                'invitation_token' => $invitation->token,
                'invited_by_username' => $invitation->invitedBy->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space invitation notification', [
                'invitation_id' => (string) $invitation->id,
                'exception' => $e,
            ]);
        }
    }
}
