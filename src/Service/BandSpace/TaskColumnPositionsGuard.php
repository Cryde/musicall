<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Reorder and move payloads carry absolute positions. A payload naming only part of a column
 * renumbers that part from 0 and leaves the tasks it omitted holding those same numbers, so the
 * board falls back on its tie-break instead of the requested order, for every member of the band
 * space, and a refresh does not repair it. That is exactly what a filtered board used to send, so
 * both write paths prove the payload covers the whole column before anything is written.
 *
 * The shape rule (unique ids forming a contiguous 0..n-1) is enforced by TaskReorderPositions and
 * TaskMovePayload; this is the membership half, which needs the band space.
 */
readonly class TaskColumnPositionsGuard
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * @param string[] $requestedIds ids carried by the payload
     * @param string|null $joiningTaskId a task arriving from another column: the payload names it
     *                                   but the column does not hold it yet
     */
    public function assertCoversColumn(
        BandSpace $bandSpace,
        TaskStatus $status,
        array $requestedIds,
        ?string $joiningTaskId = null,
    ): void {
        $columnIds = array_map(
            static fn(Task $task): string => (string) $task->id,
            $this->taskRepository->findActiveColumn($bandSpace, $status),
        );

        if ($joiningTaskId !== null) {
            $columnIds[] = $joiningTaskId;
        }

        $expectedIds = array_values(array_unique($columnIds));
        $actualIds = array_values($requestedIds);
        sort($expectedIds);
        sort($actualIds);

        if ($expectedIds !== $actualIds) {
            throw new UnprocessableEntityHttpException(
                'Les positions doivent couvrir exactement les tâches de cette colonne',
            );
        }
    }
}
