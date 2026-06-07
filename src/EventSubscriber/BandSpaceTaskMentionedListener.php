<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceTaskMentionedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the users @-mentioned in a band space task comment (#717). Best-effort per the epic #689
 * resilience contract: the event is dispatched after the comment is committed, and this listener
 * swallows + logs any failure so it can never roll back or 500 the comment. The comment author is
 * excluded (a self-mention must not self-notify); createForRecipients dedupes by user id.
 */
#[AsEventListener]
readonly class BandSpaceTaskMentionedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceTaskMentionedEvent $event): void
    {
        $comment = $event->comment;
        $actorId = (string) $comment->author->id;

        $recipients = array_filter(
            $event->mentionedUsers,
            static fn (User $user): bool => (string) $user->id !== $actorId,
        );
        if ($recipients === []) {
            return;
        }

        try {
            $task = $comment->task;
            $this->notificationCreator->createForRecipients($recipients, NotificationType::TaskMention, [
                'band_space_id' => (string) $task->bandSpace->id,
                'task_id' => (string) $task->id,
                'task_title' => $task->title,
                'comment_id' => (string) $comment->id,
                'actor_id' => $actorId,
                'actor_username' => $comment->author->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create task mention notifications', [
                'comment_id' => (string) $comment->id,
                'exception' => $e,
            ]);
        }
    }
}
