<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class TaskBulkDeleteProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * @param string[] $taskIds
     */
    public function delete(BandSpace $bandSpace, BandSpaceMembership $membership, array $taskIds, User $user): void
    {
        if (count($taskIds) === 0) {
            return;
        }

        $tasks = $this->taskRepository->findByIdsAndBandSpace($taskIds, $bandSpace);
        $foundIds = array_map(fn(\App\Entity\BandSpace\Task $task): string => (string) $task->id, $tasks);
        $missing = array_diff($taskIds, $foundIds);
        if (count($missing) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missing)));
        }

        $isAdmin = $membership->role === Role::Admin;
        foreach ($tasks as $task) {
            $isCreator = $task->createdBy !== null && $task->createdBy->id === $user->id;
            if (!$isAdmin && !$isCreator) {
                throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer ces tâches');
            }
        }

        $this->entityManager->wrapInTransaction(function () use ($tasks): void {
            foreach ($tasks as $task) {
                $this->entityManager->remove($task);
            }
        });
    }
}
