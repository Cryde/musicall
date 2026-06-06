<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceMemberRemovedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies a band space member when an admin removes them (#723). Best-effort per the epic #689
 * resilience contract: the event is dispatched after the removal is committed, and this listener
 * swallows + logs any failure so it can never roll back or 500 the removal. The actor can never be
 * the target (self-kick is forbidden in the processor); the guard is kept for symmetry.
 */
#[AsEventListener]
readonly class BandSpaceMemberRemovedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceMemberRemovedEvent $event): void
    {
        $membership = $event->membership;
        $actor = $event->actor;

        if ((string) $membership->user->id === (string) $actor->id) {
            return;
        }

        try {
            $bandSpace = $membership->bandSpace;
            $this->notificationCreator->create($membership->user, NotificationType::BandSpaceMemberRemoved, [
                'band_space_id' => (string) $bandSpace->id,
                'band_space_name' => $bandSpace->name,
                'actor_id' => (string) $actor->id,
                'actor_username' => $actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space member removed notification', [
                'membership_id' => (string) $membership->id,
                'exception' => $e,
            ]);
        }
    }
}
