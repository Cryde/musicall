<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\TaskWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\TaskColumnPositionsGuard;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class TaskMoveProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private TaskColumnPositionsGuard $columnPositionsGuard,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private TaskWriteGuard $taskWriteGuard,
    ) {
    }

    /**
     * @param array<int, array{id: string, position: int}> $positions the whole destination column,
     *        or empty to append the task to it when the client could not build that ordering
     */
    public function move(
        BandSpace $bandSpace,
        string $taskId,
        string $newStatus,
        array $positions,
        User $user,
    ): Task {
        $task = $this->taskRepository->findOneByIdAndBandSpace($taskId, $bandSpace);
        if (!$task instanceof Task) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', $taskId));
        }

        // Refused outright rather than per field: status, completedDatetime and position are the
        // three this writes, and none of them means anything on a task that is out of the board.
        $this->taskWriteGuard->assertWritable($task);

        $targetStatus = TaskStatus::from($newStatus);

        $requestedIds = array_column($positions, 'id');
        $foundTasks = $this->taskRepository->findByIdsAndBandSpace($requestedIds, $bandSpace);
        $foundIds = array_map(fn(Task $t): string => (string) $t->id, $foundTasks);

        $missingIds = array_diff($requestedIds, $foundIds);
        if (count($missingIds) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missingIds)));
        }

        if ($positions !== []) {
            $this->columnPositionsGuard->assertCoversColumn(
                $bandSpace,
                $targetStatus,
                $requestedIds,
                (string) $task->id,
            );
        }

        return $this->entityManager->wrapInTransaction(
            function () use ($bandSpace, $task, $targetStatus, $positions, $user): Task {
                $oldStatus = $task->status->value;
                if ($oldStatus !== $targetStatus->value) {
                    $task->status = $targetStatus;
                    $task->completedDatetime = $task->status === TaskStatus::Done ? new DateTimeImmutable() : null;
                    $this->bandSpaceActivityRecorder->record(
                        bandSpace: $task->bandSpace,
                        module: BandSpaceModule::Task,
                        type: BandSpaceTaskActivityType::StatusChanged,
                        resourceId: $task->id,
                        actor: $user,
                        payload: ['from' => $oldStatus, 'to' => $targetStatus->value],
                    );
                }

                if ($positions === []) {
                    // The end of the destination column. Whether or not the status change has
                    // reached the database by now, the highest position it holds plus one is above
                    // every number in use there, so the task cannot land on a taken one.
                    $task->position = $this->taskRepository->findNextPositionInColumn($bandSpace, $targetStatus);
                } else {
                    foreach ($positions as $item) {
                        if ($item['id'] === (string) $task->id) {
                            $task->position = $item['position'];
                            break;
                        }
                    }

                    $this->taskRepository->bulkUpdatePositions($positions);
                }

                $task->updateDatetime = new DateTime();

                $this->entityManager->flush();

                return $task;
            }
        );
    }
}
