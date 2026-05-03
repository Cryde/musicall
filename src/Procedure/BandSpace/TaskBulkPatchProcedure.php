<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Repository\BandSpace\TaskRepository;
use App\Service\Builder\BandSpace\TaskBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        $foundIds = array_map(fn($task): string => (string) $task->id, $tasks);
        $missing = array_diff($taskIds, $foundIds);
        if (count($missing) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missing)));
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
}
