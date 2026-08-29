<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\TaskRepository;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class TaskBulkDeleteProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
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
        $foundIds = array_map(fn(Task $task): string => (string) $task->id, $tasks);
        $missing = array_diff($taskIds, $foundIds);
        if (count($missing) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missing)));
        }

        if ($membership->role !== Role::Admin) {
            $this->assertEveryTaskIsOwnedBy($tasks, $user);
        }

        $titlesByTaskId = [];
        foreach ($tasks as $task) {
            $titlesByTaskId[(string) $task->id] = $task->title;
        }

        $this->entityManager->wrapInTransaction(function () use ($bandSpace, $tasks, $titlesByTaskId, $user): void {
            $this->fileSourceDetacher->detachDeletedSources($bandSpace, 'task', $titlesByTaskId, $user);

            foreach ($tasks as $task) {
                $this->entityManager->remove($task);
            }
        });
    }

    /**
     * The batch is one transaction, so a single task somebody else created takes the whole selection
     * down with it. Someone who ticked eight cards cannot act on that unless the answer says which
     * ones are not theirs, hence the up-front pass: it names them instead of failing on whichever the
     * loop reached first.
     *
     * @param Task[] $tasks
     */
    private function assertEveryTaskIsOwnedBy(array $tasks, User $user): void
    {
        $userId = (string) $user->id;
        $foreign = array_filter(
            $tasks,
            static fn(Task $task): bool => (string) $task->createdBy->id !== $userId,
        );

        if ($foreign === []) {
            return;
        }

        throw new AccessDeniedHttpException(sprintf(
            'Seul le créateur ou un administrateur peut supprimer ces tâches : %s',
            implode(', ', array_map(static fn(Task $task): string => $task->title, $foreign)),
        ));
    }
}
