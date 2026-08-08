<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\TaskRepository;

/**
 * Shared read-time refresh of `payload.task_title` to the live task title, so a task notification
 * stays findable on the board after the task is renamed (there is no per-task deep-link, so the
 * title is load-bearing for navigation). Batched: two queries for the whole page, whatever its size.
 * A deleted task keeps its last-known stored title (graceful staleness). Subclasses bind the type.
 *
 * The refresh follows the reader's current access (#817). Notifications outlive the membership that
 * earned them - nothing prunes the feed when somebody leaves a band space - so without this the
 * stored payload would become a live window on the titles of a board they can no longer open, one
 * that keeps updating every time they pull down their notifications. A recipient who is no longer an
 * active member keeps the title as it stood when the notification was written: their own history,
 * frozen where it belongs. Active members see exactly what they saw before.
 */
abstract readonly class AbstractTaskTitleEnricher
{
    public function __construct(
        private TaskRepository $taskRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    /**
     * @param UserNotification[] $notifications
     */
    public function enrich(array $notifications, User $recipient): void
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

        $tasks = $this->taskRepository->findByIds($ids);
        if ($tasks === []) {
            return;
        }

        // Read off the task rather than the payload: the band space a task belongs to is a fact of
        // the task, and a payload written by a past release cannot be relied on to carry it.
        $bandSpaceIds = [];
        foreach ($tasks as $task) {
            $bandSpaceId = (string) $task->bandSpace->id;
            $bandSpaceIds[$bandSpaceId] = $bandSpaceId;
        }

        $activeBandSpaceIds = $this->bandSpaceMembershipRepository->findActiveBandSpaceIdsForUser(
            $recipient,
            array_values($bandSpaceIds),
        );

        $titlesById = [];
        foreach ($tasks as $task) {
            if (isset($activeBandSpaceIds[(string) $task->bandSpace->id])) {
                $titlesById[(string) $task->id] = $task->title;
            }
        }

        foreach ($notifications as $notification) {
            $taskId = $notification->payload['task_id'] ?? null;
            if (is_string($taskId) && isset($titlesById[$taskId])) {
                $notification->payload['task_title'] = $titlesById[$taskId];
            }
        }
    }
}
