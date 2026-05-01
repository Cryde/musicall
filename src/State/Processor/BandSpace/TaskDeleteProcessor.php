<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskResource, void>
 */
readonly class TaskDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private Security $security,
    ) {
    }

    /**
     * @param TaskResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace($data->id, $membership->bandSpace);
        if (!$task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $isCreator = $task->createdBy !== null && $task->createdBy->id === $user->id;
        if (!$isCreator && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer cette tâche');
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }
}
