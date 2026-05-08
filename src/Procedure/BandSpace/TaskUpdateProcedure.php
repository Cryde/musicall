<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Repository\UserRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class TaskUpdateProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskCategoryRepository $taskCategoryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private UserRepository $userRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    /**
     * @param array<string, mixed> $payload  raw merge-patch payload used to detect explicitly-sent fields
     */
    public function update(
        Task $task,
        array $payload,
        TaskResource $data,
        BandSpace $bandSpace,
        User $user,
    ): Task {
        if (array_key_exists('title', $payload)) {
            $task->title = $data->title;
        }

        if (array_key_exists('description', $payload)) {
            $task->description = $data->description;
        }

        if (array_key_exists('status', $payload)) {
            $this->applyStatusChange($task, $data->status, $user);
        }

        if (array_key_exists('priority', $payload)) {
            $task->priority = TaskPriority::from($data->priority);
        }

        if (array_key_exists('due_date', $payload)) {
            $this->applyDueDateChange($task, $data->dueDate, $user);
        }

        if (array_key_exists('category_id', $payload)) {
            $this->applyCategoryChange($task, $data->categoryId, $bandSpace, $user);
        }

        if (array_key_exists('assignee_ids', $payload)) {
            $this->applyAssigneesChange($task, $data->assignees, $payload['assignee_ids'], $bandSpace, $user);
        }

        if (array_key_exists('archived', $payload)) {
            $this->applyArchivedChange($task, (bool) $payload['archived'], $user);
        }

        if (array_key_exists('position', $payload)) {
            $task->position = (int) $payload['position'];
        }

        $task->updateDatetime = new DateTime();

        $this->entityManager->flush();

        return $task;
    }

    private function applyStatusChange(Task $task, string $newStatus, User $user): void
    {
        $oldStatus = $task->status->value;
        if ($oldStatus === $newStatus) {
            return;
        }

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

    private function applyDueDateChange(Task $task, ?string $newDueDate, User $user): void
    {
        $oldDueDate = $task->dueDate?->format('Y-m-d');
        if ($oldDueDate === $newDueDate) {
            return;
        }

        $task->dueDate = $newDueDate !== null ? new DateTimeImmutable($newDueDate) : null;
        $this->bandSpaceActivityRecorder->record(
            bandSpace: $task->bandSpace,
            module: BandSpaceModule::Task,
            type: 'due_date_changed',
            resourceId: $task->id,
            actor: $user,
            payload: ['from' => $oldDueDate, 'to' => $newDueDate],
        );
    }

    private function applyCategoryChange(Task $task, ?string $newCategoryId, BandSpace $bandSpace, User $user): void
    {
        $oldCategoryId = $task->category !== null ? (string) $task->category->id : null;
        if ($newCategoryId !== null) {
            $category = $this->taskCategoryRepository->findOneByIdAndBandSpace($newCategoryId, $bandSpace);
            if (!$category) {
                throw new NotFoundHttpException('Catégorie introuvable');
            }
            $task->category = $category;
        } else {
            $task->category = null;
        }
        if ($oldCategoryId === $newCategoryId) {
            return;
        }

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $task->bandSpace,
            module: BandSpaceModule::Task,
            type: 'category_changed',
            resourceId: $task->id,
            actor: $user,
            payload: ['from' => $oldCategoryId, 'to' => $newCategoryId],
        );
    }

    /**
     * @param array<int, array{id: string, username: string}> $currentAssignees
     * @param string[] $newAssigneeIds
     */
    private function applyAssigneesChange(
        Task $task,
        array $currentAssignees,
        array $newAssigneeIds,
        BandSpace $bandSpace,
        User $user,
    ): void {
        $oldIds = array_map(fn(array $a): string => $a['id'], $currentAssignees);
        $added = array_diff($newAssigneeIds, $oldIds);
        $removed = array_diff($oldIds, $newAssigneeIds);

        foreach ($removed as $removedId) {
            foreach ($task->assignees as $assignee) {
                if ((string) $assignee->id === $removedId) {
                    $task->assignees->removeElement($assignee);
                    $this->bandSpaceActivityRecorder->record(
                        bandSpace: $task->bandSpace,
                        module: BandSpaceModule::Task,
                        type: 'assignee_removed',
                        resourceId: $task->id,
                        actor: $user,
                        payload: [
                            'assignee_id' => $assignee->id,
                            'assignee_username' => $assignee->username,
                        ],
                    );
                    break;
                }
            }
        }

        foreach ($added as $addedId) {
            $assignee = $this->userRepository->find($addedId);
            if (!$assignee) {
                throw new BadRequestHttpException(sprintf('Utilisateur %s introuvable', $addedId));
            }

            $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $assignee);
            if (!$membership) {
                throw new BadRequestHttpException(sprintf('L\'utilisateur %s n\'est pas membre du Band Space', $assignee->username));
            }

            $task->assignees->add($assignee);
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $task->bandSpace,
                module: BandSpaceModule::Task,
                type: 'assignee_added',
                resourceId: $task->id,
                actor: $user,
                payload: [
                    'assignee_id' => $assignee->id,
                    'assignee_username' => $assignee->username,
                ],
            );
        }
    }

    private function applyArchivedChange(Task $task, bool $archived, User $user): void
    {
        if ($archived) {
            if ($task->archiveDatetime !== null) {
                return;
            }
            if ($task->status !== TaskStatus::Done) {
                throw new HttpException(422, 'Seules les tâches terminées peuvent être archivées');
            }
            $task->archiveDatetime = new DateTimeImmutable();
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $task->bandSpace,
                module: BandSpaceModule::Task,
                type: 'task_archived',
                resourceId: $task->id,
                actor: $user,
            );

            return;
        }

        if ($task->archiveDatetime === null) {
            return;
        }
        $task->archiveDatetime = null;
        $this->bandSpaceActivityRecorder->record(
            bandSpace: $task->bandSpace,
            module: BandSpaceModule::Task,
            type: 'task_unarchived',
            resourceId: $task->id,
            actor: $user,
        );
    }
}
