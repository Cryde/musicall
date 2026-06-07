<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Repository\BandSpace\TaskRepository;

/**
 * Shared read-time refresh of `payload.task_title` to the live task title, so a task notification
 * stays findable on the board after the task is renamed (there is no per-task deep-link, so the
 * title is load-bearing for navigation). Batched: one `id IN (...)` query for the whole page.
 * A deleted task keeps its last-known stored title (graceful staleness). Subclasses bind the type.
 */
abstract readonly class AbstractTaskTitleEnricher
{
    public function __construct(private TaskRepository $taskRepository)
    {
    }

    /**
     * @param UserNotification[] $notifications
     */
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
