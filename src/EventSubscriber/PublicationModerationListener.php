<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\Moderation\ModerationOutcome;
use App\Enum\Notification\NotificationType;
use App\Event\PublicationModeratedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the author when a moderator approves or rejects their pending publication or course (#720).
 * Best-effort per the epic #689 resilience contract: the event is dispatched after the moderation is
 * committed, and this listener swallows + logs any failure so it can never roll back or 500 the
 * approve/reject action. The moderator is never notified (approving your own publication self-excludes).
 *
 * No enricher (epic enricher rule): the approved deep-link slug is finalized at approval and never
 * re-slugged, and a rejected publication has no public page - so no payload field is both mutable and
 * load-bearing; the point-in-time payload is enough.
 */
#[AsEventListener]
readonly class PublicationModerationListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PublicationModeratedEvent $event): void
    {
        $publication = $event->publication;
        $moderator = $event->moderator;

        try {
            // Never self-notify (contract item 2): a moderator approving/rejecting their own publication.
            if ((string) $publication->author->id === (string) $moderator->id) {
                return;
            }

            $type = $event->outcome === ModerationOutcome::Approved
                ? NotificationType::PublicationApproved
                : NotificationType::PublicationRejected;

            $this->notificationCreator->create($publication->author, $type, [
                'publication_id' => (string) $publication->id,
                'publication_slug' => $publication->slug,
                'publication_title' => $publication->title,
                'is_course' => $publication->subCategory->getIsCourse(),
                'actor_id' => (string) $moderator->id,
                'actor_username' => $moderator->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create publication moderation notification', [
                'publication_id' => (string) $publication->id,
                'exception' => $e,
            ]);
        }
    }
}
