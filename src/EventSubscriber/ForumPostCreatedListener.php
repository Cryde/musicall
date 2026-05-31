<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\ForumPostCreatedEvent;
use App\Repository\Forum\ForumTopicParticipationRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the topic author + active participants when a forum reply is posted (#715).
 * Best-effort per the epic #689 resilience contract: the event is dispatched after the
 * post is committed, and this listener swallows + logs any failure so it can never roll
 * back or 500 the reply. The poster is excluded; createForRecipients dedupes (an author
 * who is also a participant gets a single row).
 */
#[AsEventListener]
readonly class ForumPostCreatedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private ForumTopicParticipationRepository $participationRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ForumPostCreatedEvent $event): void
    {
        $post = $event->forumPost;

        // Whole body (incl. the recipient-resolution query) wrapped: a failure here must
        // never roll back or 500 the reply (contract item 1) - log and move on.
        try {
            $topic = $post->topic;
            $poster = $post->creator;
            $posterId = (string) $poster->id;

            $candidates = [$topic->author, ...$this->participationRepository->findActiveParticipantUsersByTopic($topic)];
            $recipients = array_filter($candidates, static fn (User $user): bool => (string) $user->id !== $posterId);
            if ($recipients === []) {
                return;
            }

            // topic_slug/topic_title are stored point-in-time. Safe today: forum topics have no
            // edit/rename flow (immutable), so the slug link never breaks and the title never goes
            // stale. If topic editing is ever added, refresh these at feed-read via a
            // NotificationEnricher (cf. BandSpaceTaskNotificationEnricher) or keep the slug stable.
            $this->notificationCreator->createForRecipients($recipients, NotificationType::ForumTopicReply, [
                'topic_id' => (string) $topic->id,
                'topic_slug' => $topic->slug,
                'topic_title' => $topic->title,
                'post_id' => (string) $post->id,
                'actor_id' => $posterId,
                'actor_username' => $poster->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create forum reply notifications', [
                'post_id' => (string) $post->id,
                'exception' => $e,
            ]);
        }
    }
}
