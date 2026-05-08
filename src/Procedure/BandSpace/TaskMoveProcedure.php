<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class TaskMoveProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    /**
     * @param array<int, array{id: string, position: int}> $positions
     */
    public function move(
        BandSpace $bandSpace,
        string $taskId,
        string $newStatus,
        array $positions,
        User $user,
    ): Task {
        $task = $this->taskRepository->findOneByIdAndBandSpace($taskId, $bandSpace);
        if (!$task) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', $taskId));
        }

        $requestedIds = array_column($positions, 'id');
        $foundTasks = $this->taskRepository->findByIdsAndBandSpace($requestedIds, $bandSpace);
        $foundIds = array_map(fn(Task $t): string => (string) $t->id, $foundTasks);

        $missingIds = array_diff($requestedIds, $foundIds);
        if (count($missingIds) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missingIds)));
        }

        return $this->entityManager->wrapInTransaction(function () use ($task, $newStatus, $positions, $user): Task {
            $oldStatus = $task->status->value;
            if ($oldStatus !== $newStatus) {
                $task->status = TaskStatus::from($newStatus);
                if ($task->status === TaskStatus::Done) {
                    $task->completedDatetime = new DateTimeImmutable();
                } else {
                    $task->completedDatetime = null;
                }
                $this->bandSpaceActivityRecorder->record(
                    bandSpace: $task->bandSpace,
                    module: BandSpaceModule::Task,
                    type: 'status_changed',
                    resourceId: $task->id,
                    actor: $user,
                    payload: ['from' => $oldStatus, 'to' => $newStatus],
                );
            }

            foreach ($positions as $item) {
                if ($item['id'] === (string) $task->id) {
                    $task->position = $item['position'];
                    break;
                }
            }

            $task->updateDatetime = new DateTime();

            $this->taskRepository->bulkUpdatePositions($positions);

            $this->entityManager->flush();

            return $task;
        });
    }
}
