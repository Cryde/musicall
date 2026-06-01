<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Comment\Comment;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\PublicationCommentedEvent;
use App\Repository\Comment\CommentRepository;
use App\Repository\PublicationRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the publication author + everyone who has commented in the thread (and, on a reply,
 * the parent comment author) when a new comment is posted on a publication or course (#716).
 * Best-effort per the epic #689 resilience contract: the event is dispatched after the comment
 * is committed, and this listener swallows + logs any failure so it can never roll back or 500
 * the comment. The commenter is excluded; createForRecipients dedupes (an author who also
 * commented, or who is the parent author, gets a single row).
 */
#[AsEventListener]
readonly class PublicationCommentedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private PublicationRepository $publicationRepository,
        private CommentRepository $commentRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PublicationCommentedEvent $event): void
    {
        $comment = $event->comment;

        // Whole body (incl. the publication + author-resolution queries) wrapped: a failure here
        // must never roll back or 500 the comment (contract item 1) - log and move on.
        try {
            $publication = $this->publicationRepository->findOneBy(['thread' => $comment->thread]);
            if (!$publication instanceof Publication) {
                // Only Publication carries a CommentThread today, so a thread without one is not
                // a publication/course comment (e.g. a future commentable) - nothing to notify.
                return;
            }

            $actor = $comment->author;
            $actorId = (string) $actor->id;

            $candidates = [$publication->author, ...$this->commentRepository->findThreadAuthors($comment->thread)];
            if ($comment->parent instanceof Comment) {
                $candidates[] = $comment->parent->author;
            }

            $recipients = array_filter($candidates, static fn (User $user): bool => (string) $user->id !== $actorId);
            if ($recipients === []) {
                return;
            }

            // publication_slug is stable (frozen at creation as slug(title)-{id}, never re-slugged
            // on edit), so the deep-link never breaks and publication_title may stay point-in-time
            // (cosmetic). If publications ever get re-slugged on edit, refresh these at feed-read via
            // a NotificationEnricher (cf. BandSpaceTaskNotificationEnricher).
            $type = $comment->parent instanceof Comment
                ? NotificationType::CommentReply
                : NotificationType::PublicationComment;

            $this->notificationCreator->createForRecipients($recipients, $type, [
                'publication_id' => (string) $publication->id,
                'publication_slug' => $publication->slug,
                'publication_title' => $publication->title,
                'is_course' => $publication->subCategory->getIsCourse(),
                'comment_id' => (string) $comment->id,
                'actor_id' => $actorId,
                'actor_username' => $actor->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create publication comment notifications', [
                'comment_id' => (string) $comment->id,
                'exception' => $e,
            ]);
        }
    }
}
