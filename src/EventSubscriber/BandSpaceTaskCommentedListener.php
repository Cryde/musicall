<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceTaskCommentedEvent;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the participants of a band space task - its creator, its assignees and everyone who has
 * already commented - when a new comment is posted (#727). Users @-mentioned in the same comment are
 * excluded: they get the richer task-mention notification (#717) instead of a duplicate. Best-effort
 * per the epic #689 resilience contract: the event is dispatched after the comment is committed, and
 * the whole body (incl. the prior-commenter query) is wrapped so a failure can never roll back or
 * 500 the comment. The comment author is excluded; createForRecipients dedupes by user id.
 */
#[AsEventListener]
readonly class BandSpaceTaskCommentedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private TaskCommentRepository $taskCommentRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceTaskCommentedEvent $event): void
    {
        $comment = $event->comment;
        $task = $comment->task;

        $excludedIds = [(string) $comment->author->id => true];
        foreach ($event->mentionedUsers as $mentionedUser) {
            $excludedIds[(string) $mentionedUser->id] = true;
        }

        try {
            $candidates = [
                $task->createdBy,
                ...$task->assignees->toArray(),
                ...$this->taskCommentRepository->findCommentAuthorsByTask($task),
            ];

            $recipients = array_filter(
                $candidates,
                static fn (?User $user): bool => $user instanceof User && !isset($excludedIds[(string) $user->id]),
            );
            if ($recipients === []) {
                return;
            }

            $this->notificationCreator->createForRecipients($recipients, NotificationType::TaskComment, [
                'band_space_id' => (string) $task->bandSpace->id,
                'task_id' => (string) $task->id,
                'task_title' => $task->title,
                'comment_id' => (string) $comment->id,
                'actor_id' => (string) $comment->author->id,
                'actor_username' => $comment->author->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create task comment notifications', [
                'comment_id' => (string) $comment->id,
                'exception' => $e,
            ]);
        }
    }
}
