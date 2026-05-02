<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskReorder;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TaskReorder, void>
 */
readonly class TaskReorderProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private Security $security,
    ) {
    }

    /**
     * @param TaskReorder $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $requestedIds = array_column($data->positions, 'id');
        $foundTasks = $this->taskRepository->findByIdsAndBandSpace($requestedIds, $bandSpace);
        $foundIds = array_map(fn($task): string => (string) $task->id, $foundTasks);

        $missingIds = array_diff($requestedIds, $foundIds);
        if (count($missingIds) > 0) {
            throw new BadRequestHttpException(sprintf('Tâche %s introuvable dans ce Band Space', reset($missingIds)));
        }

        $statuses = array_unique(array_map(fn(Task $t): string => $t->status->value, $foundTasks));
        if (count($statuses) > 1) {
            throw new UnprocessableEntityHttpException('Toutes les tâches doivent avoir le même statut');
        }

        $this->taskRepository->bulkUpdatePositions($data->positions);
    }
}
