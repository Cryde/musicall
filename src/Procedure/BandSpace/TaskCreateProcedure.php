<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\ApiResource\BandSpace\Task\TaskCreate;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\BandSpace\TaskCategory;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Event\BandSpaceTaskAssignedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Repository\UserRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The one way a task comes into existence.
 *
 * Extracted from TaskCreateProcessor when the chat gained « créer une tâche » (#979): two doors onto
 * the board must not mean two notions of what a new task is, or the one nobody looks at drifts.
 * Callers do the authorization, this does the writing.
 */
readonly class TaskCreateProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskCategoryRepository $taskCategoryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private UserRepository $userRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function create(TaskCreate $input, BandSpace $bandSpace, User $author): Task
    {
        $task = new Task();
        $task->bandSpace = $bandSpace;
        $task->title = $input->title;
        $task->description = $input->description;
        $task->status = TaskStatus::from($input->status);
        if ($task->status === TaskStatus::Done) {
            $task->completedDatetime = new DateTimeImmutable();
        }
        $task->priority = TaskPriority::from($input->priority);
        $task->createdBy = $author;

        if ($input->dueDate !== null) {
            $task->dueDate = new DateTimeImmutable($input->dueDate);
        }

        if ($input->categoryId !== null) {
            $category = $this->taskCategoryRepository->findOneByIdAndBandSpace($input->categoryId, $bandSpace);
            if (!$category instanceof TaskCategory) {
                throw new NotFoundHttpException('Catégorie introuvable');
            }
            $task->category = $category;
        }

        $this->entityManager->persist($task);

        $addedAssignees = $this->addAssignees($task, $input->assigneeIds ?? [], $bandSpace, $author);

        $this->entityManager->flush();

        if ($addedAssignees !== []) {
            $this->eventDispatcher->dispatch(new BandSpaceTaskAssignedEvent($task, $author, $addedAssignees));
        }

        return $task;
    }

    /**
     * @param string[] $assigneeIds
     *
     * @return User[]
     */
    private function addAssignees(Task $task, array $assigneeIds, BandSpace $bandSpace, User $author): array
    {
        $addedAssignees = [];
        foreach ($assigneeIds as $assigneeId) {
            $assignee = $this->userRepository->find($assigneeId);
            if (!$assignee instanceof User) {
                throw new BadRequestHttpException(sprintf('Utilisateur %s introuvable', $assigneeId));
            }

            $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $assignee);
            if (!$membership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
                throw new BadRequestHttpException(sprintf('L\'utilisateur %s n\'est pas membre du Band Space', $assignee->username));
            }

            $task->assignees->add($assignee);
            $addedAssignees[] = $assignee;
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $task->bandSpace,
                module: BandSpaceModule::Task,
                type: BandSpaceTaskActivityType::AssigneeAdded,
                resourceId: $task->id,
                actor: $author,
                payload: [
                    'assignee_id' => $assignee->id,
                    'assignee_username' => $assignee->username,
                ],
            );
        }

        return $addedAssignees;
    }
}
