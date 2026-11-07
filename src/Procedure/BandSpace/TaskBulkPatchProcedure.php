<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\TaskStatus;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\BandSpace\TaskBuilder;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class TaskBulkPatchProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private TaskUpdateProcedure $taskUpdateProcedure,
        private TaskBuilder $taskBuilder,
    ) {
    }

    /**
     * @param string[] $taskIds
     * @param array<string, mixed> $patchPayload  raw merge-patch fields applied to every task (presence-detected)
     */
    public function patch(BandSpace $bandSpace, array $taskIds, array $patchPayload, User $user): void
    {
        if (count($taskIds) === 0) {
            return;
        }

        $tasks = $this->taskRepository->findByIdsAndBandSpace($taskIds, $bandSpace);
        $foundIds = array_map(fn(Task $task): string => (string) $task->id, $tasks);
        $missing = array_diff($taskIds, $foundIds);
        if (count($missing) > 0) {
            throw new TranslatableHttpException(
                Response::HTTP_BAD_REQUEST,
                'band_space.task.not_found_in_space',
                ['id' => (string) reset($missing)],
            );
        }

        if (array_key_exists('archived', $patchPayload) && (bool) $patchPayload['archived']) {
            $this->assertEveryTaskIsDone($tasks);
        }

        $targetStatus = array_key_exists('status', $patchPayload) ? TaskStatus::from($patchPayload['status']) : null;

        $this->entityManager->wrapInTransaction(function () use ($tasks, $patchPayload, $targetStatus, $bandSpace, $user): void {
            if ($targetStatus instanceof TaskStatus) {
                $this->appendToColumn($tasks, $targetStatus, $bandSpace);
            }

            foreach ($tasks as $task) {
                $resource = $this->taskBuilder->buildItem($task);
                if (array_key_exists('category_id', $patchPayload)) {
                    $resource->categoryId = $patchPayload['category_id'];
                }
                if ($targetStatus instanceof TaskStatus) {
                    $resource->status = $targetStatus->value;
                }

                $this->taskUpdateProcedure->update($task, $patchPayload, $resource, $bandSpace, $user);
            }
        });
    }

    /**
     * A status change alone keeps the task's old index, which a card of the new column may already
     * hold. The moved tasks go after that column's last card instead, in board order. The end is read
     * once and counted up from: every position is handed out before anything is flushed.
     *
     * @param Task[] $tasks
     */
    private function appendToColumn(array $tasks, TaskStatus $targetStatus, BandSpace $bandSpace): void
    {
        $moving = array_filter($tasks, static fn(Task $task): bool => $task->status !== $targetStatus);
        usort($moving, self::compareBoardOrder(...));

        $nextPosition = $this->taskRepository->findNextPositionInColumn($bandSpace, $targetStatus);
        foreach ($moving as $task) {
            $task->position = $nextPosition++;
        }
    }

    /**
     * Column by column, left to right as the enum lists them, then top to bottom as each column shows
     * its cards: Terminé by completion, newest first (TaskColumn.vue), the others by position.
     */
    private static function compareBoardOrder(Task $a, Task $b): int
    {
        $columns = TaskStatus::cases();
        $byColumn = array_search($a->status, $columns, true) <=> array_search($b->status, $columns, true);
        if ($byColumn !== 0) {
            return $byColumn;
        }

        if ($a->status === TaskStatus::Done) {
            $byCompletion = self::shownCompletion($b) <=> self::shownCompletion($a);
            if ($byCompletion !== 0) {
                return $byCompletion;
            }
        }

        return $a->position <=> $b->position;
    }

    /** The date the Terminé column sorts on, with the same fallbacks. */
    private static function shownCompletion(Task $task): DateTimeInterface
    {
        return $task->completedDatetime ?? $task->updateDatetime ?? $task->creationDatetime;
    }

    /**
     * The batch is one transaction, so a single task that is not done takes the whole selection down
     * with it. Somebody who ticked eight cards cannot act on that unless the answer says which ones
     * are in the way, hence the up-front pass: it names them instead of failing on whichever the
     * loop reached first.
     *
     * @param Task[] $tasks
     */
    private function assertEveryTaskIsDone(array $tasks): void
    {
        // An already archived task is left out: archiving it again is a no-op, never a refusal.
        $blocking = array_filter(
            $tasks,
            static fn(Task $task): bool => $task->status !== TaskStatus::Done
                && !$task->archiveDatetime instanceof DateTimeImmutable,
        );

        if ($blocking === []) {
            return;
        }

        throw new TranslatableHttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'band_space.task.bulk_archive_requires_done',
            ['titles' => implode(', ', array_map(static fn(Task $task): string => $task->title, $blocking))],
        );
    }
}
