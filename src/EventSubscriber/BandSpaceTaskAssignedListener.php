<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceTaskAssignedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the newly-added assignees of a band space task (#721). Best-effort per
 * the epic #689 resilience contract: the event is dispatched after the task is
 * committed, and this listener swallows + logs any failure so it can never roll
 * back or 500 the task write. The actor (the assigner) is excluded.
 */
#[AsEventListener]
readonly class BandSpaceTaskAssignedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceTaskAssignedEvent $event): void
    {
        $actorId = (string) $event->actor->id;
        $recipients = array_filter(
            $event->assignees,
            static fn (User $assignee): bool => (string) $assignee->id !== $actorId,
        );

        if ($recipients === []) {
            return;
        }

        try {
            $this->notificationCreator->createForRecipients($recipients, NotificationType::BandSpaceTaskAssignment, [
                'band_space_id' => (string) $event->task->bandSpace->id,
                'task_id' => (string) $event->task->id,
                'task_title' => $event->task->title,
                'actor_id' => $actorId,
                'actor_username' => $event->actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create band space task assignment notifications', [
                'task_id' => (string) $event->task->id,
                'exception' => $e,
            ]);
        }
    }
}
