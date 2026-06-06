<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceMemberRoleChangedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies a band space member when an admin changes their role (#723). Best-effort per the epic #689
 * resilience contract: the event is dispatched after the change is committed, and this listener
 * swallows + logs any failure so it can never roll back or 500 the role update.
 *
 * Actor exclusion is required (not merely defensive): a member can demote their own role while another
 * admin exists, so actor == recipient is reachable here and must not self-notify.
 */
#[AsEventListener]
readonly class BandSpaceMemberRoleChangedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceMemberRoleChangedEvent $event): void
    {
        $membership = $event->membership;
        $actor = $event->actor;

        if ((string) $membership->user->id === (string) $actor->id) {
            return;
        }

        try {
            $bandSpace = $membership->bandSpace;
            $this->notificationCreator->create($membership->user, NotificationType::BandSpaceRoleChanged, [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => $bandSpace->name,
                'from' => $event->oldRole->value,
                'to' => $membership->role->value,
                'actor_id' => (string) $actor->id,
                'actor_username' => $actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space role changed notification', [
                'membership_id' => (string) $membership->id,
                'exception' => $e,
            ]);
        }
    }
}
