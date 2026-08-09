<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\BandSpace\TaskBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missing)));
        }

        if (array_key_exists('archived', $patchPayload) && (bool) $patchPayload['archived']) {
            $this->assertEveryTaskIsDone($tasks);
        }

        $this->entityManager->wrapInTransaction(function () use ($tasks, $patchPayload, $bandSpace, $user): void {
            foreach ($tasks as $task) {
                $resource = $this->taskBuilder->buildItem($task);
                if (array_key_exists('category_id', $patchPayload)) {
                    $resource->categoryId = $patchPayload['category_id'];
                }

                $this->taskUpdateProcedure->update($task, $patchPayload, $resource, $bandSpace, $user);
            }
        });
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

        throw new UnprocessableEntityHttpException(sprintf(
            'Seules les tâches terminées peuvent être archivées : %s',
            implode(', ', array_map(static fn(Task $task): string => $task->title, $blocking)),
        ));
    }
}
