<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\TaskRepository;

/**
 * Refreshes `payload.task_title` to the live task title when the feed is read, so a
 * task-assignment notification stays findable on the board after the task is renamed
 * (there is no per-task deep-link, so the title is load-bearing for navigation).
 * Batched: one `id IN (...)` query for the whole page. A deleted task keeps its
 * last-known stored title (graceful staleness).
 */
readonly class BandSpaceTaskNotificationEnricher implements NotificationEnricherInterface
{
    public function __construct(private TaskRepository $taskRepository)
    {
    }

    public function getType(): NotificationType
    {
        return NotificationType::BandSpaceTaskAssignment;
    }

    public function enrich(array $notifications): void
    {
        $ids = [];
        foreach ($notifications as $notification) {
            $taskId = $notification->payload['task_id'] ?? null;
            if (is_string($taskId)) {
                $ids[] = $taskId;
            }
        }

        if ($ids === []) {
            return;
        }

        $titlesById = [];
        foreach ($this->taskRepository->findByIds($ids) as $task) {
            $titlesById[(string) $task->id] = $task->title;
        }

        foreach ($notifications as $notification) {
            $taskId = $notification->payload['task_id'] ?? null;
            if (is_string($taskId) && isset($titlesById[$taskId])) {
                $notification->payload['task_title'] = $titlesById[$taskId];
            }
        }
    }
}
