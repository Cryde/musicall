<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Enum\BandSpace\Role;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\TaskRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class TaskBulkDeleteProcedure
{
    /** The feed names the first few, so a long selection still reads as one sentence. */
    private const int TITLES_IN_ACTIVITY = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
        private BandSpaceActivityRecorder $activityRecorder,
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
            throw new TranslatableHttpException(
                Response::HTTP_BAD_REQUEST,
                'band_space.task.not_found_in_space',
                ['id' => (string) reset($missing)],
            );
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

            // One line for the whole selection, so a large one cannot push everything else off the dashboard.
            $this->activityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Task,
                type: BandSpaceTaskActivityType::TasksDeleted,
                actor: $user,
                payload: [
                    'count' => count($tasks),
                    'titles' => array_slice(array_values($titlesByTaskId), 0, self::TITLES_IN_ACTIVITY),
                ],
            );

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

        throw new TranslatableHttpException(
            Response::HTTP_FORBIDDEN,
            'band_space.task.bulk_delete_not_allowed',
            ['titles' => implode(', ', array_map(static fn(Task $task): string => $task->title, $foreign))],
        );
    }
}
