<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\Enum\Notification\NotificationType;

/**
 * Refreshes a band-space task-assignment notification's `task_title` at feed-read (#721).
 * Shared refresh logic lives in {@see AbstractTaskTitleEnricher}.
 */
readonly class BandSpaceTaskNotificationEnricher extends AbstractTaskTitleEnricher implements NotificationEnricherInterface
{
    public function getType(): NotificationType
    {
        return NotificationType::BandSpaceTaskAssignment;
    }
}
