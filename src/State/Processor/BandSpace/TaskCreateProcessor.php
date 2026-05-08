<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCreate;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Repository\UserRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TaskBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCreate, TaskResource>
 */
readonly class TaskCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private UserRepository $userRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private TaskBuilder $taskBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = new Task();
        $task->bandSpace = $bandSpace;
        $task->title = $data->title;
        $task->description = $data->description;
        $task->status = TaskStatus::from($data->status);
        if ($task->status === TaskStatus::Done) {
            $task->completedDatetime = new \DateTimeImmutable();
        }
        $task->priority = TaskPriority::from($data->priority);
        $task->createdBy = $user;

        if ($data->dueDate !== null) {
            $task->dueDate = new \DateTimeImmutable($data->dueDate);
        }

        if ($data->categoryId !== null) {
            $category = $this->taskCategoryRepository->findOneByIdAndBandSpace($data->categoryId, $bandSpace);
            if (!$category) {
                throw new NotFoundHttpException('Catégorie introuvable');
            }
            $task->category = $category;
        }

        $this->entityManager->persist($task);

        if ($data->assigneeIds !== null) {
            foreach ($data->assigneeIds as $assigneeId) {
                $assignee = $this->userRepository->find($assigneeId);
                if (!$assignee) {
                    throw new BadRequestHttpException(sprintf('Utilisateur %s introuvable', $assigneeId));
                }

                $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $assignee);
                if (!$membership) {
                    throw new BadRequestHttpException(sprintf('L\'utilisateur %s n\'est pas membre du Band Space', $assignee->username));
                }

                $task->assignees->add($assignee);
                $this->bandSpaceActivityRecorder->record(
                    bandSpace: $task->bandSpace,
                    module: BandSpaceModule::Task,
                    type: BandSpaceTaskActivityType::AssigneeAdded,
                    resourceId: $task->id,
                    actor: $user,
                    payload: [
                        'assignee_id' => $assignee->id,
                        'assignee_username' => $assignee->username,
                    ],
                );
            }
        }

        $this->entityManager->flush();

        return $this->taskBuilder->buildItem($task);
    }
}
