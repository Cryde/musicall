<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\Moderation\ModerationOutcome;
use App\Enum\Notification\NotificationType;
use App\Event\GalleryModeratedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the author when a moderator approves or rejects their pending gallery (#720).
 * Same best-effort contract (#689) and moderator self-exclusion as PublicationModerationListener.
 * No enricher: the approved slug is stable and a rejected gallery has no public page.
 */
#[AsEventListener]
readonly class GalleryModerationListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GalleryModeratedEvent $event): void
    {
        $gallery = $event->gallery;
        $moderator = $event->moderator;

        try {
            // Never self-notify (contract item 2): a moderator approving/rejecting their own gallery.
            if ((string) $gallery->author->id === (string) $moderator->id) {
                return;
            }

            $type = $event->outcome === ModerationOutcome::Approved
                ? NotificationType::GalleryApproved
                : NotificationType::GalleryRejected;

            $this->notificationCreator->create($gallery->author, $type, [
                'gallery_id' => (string) $gallery->id,
                'gallery_slug' => $gallery->slug,
                'gallery_title' => $gallery->title,
                'actor_id' => (string) $moderator->id,
                'actor_username' => $moderator->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create gallery moderation notification', [
                'gallery_id' => (string) $gallery->id,
                'exception' => $e,
            ]);
        }
    }
}
