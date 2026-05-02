<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private TaskCommentRepository $taskCommentRepository,
        private TaskBuilder $taskBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return TaskResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $filters = $context['filters'] ?? [];

        $archived = isset($filters['archived']) ? filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN) : null;

        $tasks = $this->taskRepository->findByBandSpace(
            $bandSpace,
            $filters['status'] ?? null,
            $filters['category_id'] ?? null,
            $filters['assignee_id'] ?? null,
            $filters['priority'] ?? null,
            $archived,
        );

        $taskIds = array_map(fn(Task $task): string => (string) $task->id, $tasks);
        $commentCounts = $this->taskCommentRepository->countByTaskIds($taskIds);

        return $this->taskBuilder->buildFromList($tasks, $commentCounts);
    }
}
