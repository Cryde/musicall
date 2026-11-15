<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Notification\Notification;
use App\Enum\Notification\NotificationType;
use App\Event\PublicationVotedEvent;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Service\User\UserNotificationPreferenceChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Tells the author their publication or course got up votes (#1081), in one row per publication until
 * they read it: a vote is cheap, a row each would flood the bell. Best-effort per the epic #689
 * contract: dispatched after the vote is flushed, a failure is logged and never fails the vote.
 */
#[AsEventListener]
readonly class PublicationVotedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private NotificationRepository $notificationRepository,
        private UserNotificationPreferenceChecker $preferenceChecker,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PublicationVotedEvent $event): void
    {
        $publication = $event->publication;
        $author = $publication->author;

        try {
            if ($event->voter?->id === $author->id
                || $author->isDeleted()
                || !$this->preferenceChecker->canReceivePublicationVoteNotification($author)) {
                return;
            }

            $publicationId = (string) $publication->id;
            $unread = $this->notificationRepository->findUnreadPublicationVoted($author, $publicationId);

            // Title and slug are taken again on each vote, so a grouped row shows the publication as it is now.
            $payload = [
                'publication_id' => $publicationId,
                'publication_slug' => $publication->slug,
                'publication_title' => $publication->title,
                'is_course' => $publication->subCategory->getIsCourse(),
                'vote_count' => (int) ($unread?->payload['vote_count'] ?? 0) + 1,
            ];

            if ($unread instanceof Notification) {
                $this->notificationCreator->resurface($unread, $payload);
            } else {
                $this->notificationCreator->create($author, NotificationType::PublicationVoted, $payload);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create publication vote notification', [
                'publication_id' => (string) $publication->id,
                'exception' => $e,
            ]);
        }
    }
}
