<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\Enum\Notification\NotificationType;

/**
 * Refreshes a task-comment notification's `task_title` at feed-read (#727), so the notification stays
 * findable on the board after the task is renamed. Shared logic in {@see AbstractTaskTitleEnricher}.
 */
readonly class TaskCommentNotificationEnricher extends AbstractTaskTitleEnricher implements NotificationEnricherInterface
{
    public function getType(): NotificationType
    {
        return NotificationType::TaskComment;
    }
}
