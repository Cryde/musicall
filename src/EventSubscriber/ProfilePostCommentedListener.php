<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\ProfilePostCommentedEvent;
use App\Service\Notification\NotificationCreator;
use App\Service\User\Relation\UserContactPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * A comment on a wall post tells the post's author; a reply tells the comment's author (#1316). The
 * commenter, a closed account and anyone blocked either way with the commenter hear nothing. Logged,
 * never thrown: the comment is already stored.
 */
#[AsEventListener]
readonly class ProfilePostCommentedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private UserContactPolicy $contactPolicy,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProfilePostCommentedEvent $event): void
    {
        $comment = $event->comment;

        try {
            $actor = $comment->author;
            $postAuthor = $comment->post->author;
            $parentAuthor = $comment->parent instanceof ProfilePostComment ? $comment->parent->author : null;

            // The reply says more than the comment: whoever wrote the parent hears only of the reply.
            $this->notify($actor, $parentAuthor, NotificationType::ProfilePostCommentReply, $comment);
            if ($parentAuthor?->id !== $postAuthor->id) {
                $this->notify($actor, $postAuthor, NotificationType::ProfilePostComment, $comment);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create profile post comment notifications', [
                'comment_id' => (string) $comment->id,
                'exception' => $e,
            ]);
        }
    }

    private function notify(User $actor, ?User $recipient, NotificationType $type, ProfilePostComment $comment): void
    {
        if (!$recipient instanceof User || $recipient->id === $actor->id || $recipient->isDeleted()) {
            return;
        }
        $recipients = $this->contactPolicy->withoutBlocked($actor, [$recipient]);
        $this->notificationCreator->createForRecipients($recipients, $type, [
            'post_id' => (string) $comment->post->id,
            'comment_id' => (string) $comment->id,
            // Refreshed at feed read by AbstractProfilePostEnricher: a username can change.
            'wall_username' => $comment->post->author->username,
            'actor_id' => (string) $actor->id,
            'actor_username' => $actor->username,
        ]);
    }
}
