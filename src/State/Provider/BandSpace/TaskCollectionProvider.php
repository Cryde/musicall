<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\Filter\TaskFilter;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use DateTimeImmutable;
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
        private BandSpaceFileAttachmentRepository $fileAttachmentRepository,
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

        $archived = isset($filters['archived']) && filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN);
        $overdueOnly = isset($filters['overdue']) && filter_var($filters['overdue'], FILTER_VALIDATE_BOOLEAN);

        $taskFilter = new TaskFilter(
            status: $filters['status'] ?? null,
            categoryId: $filters['category_id'] ?? null,
            assigneeId: $filters['assignee_id'] ?? null,
            priority: $filters['priority'] ?? null,
            archived: $archived,
            query: $filters['query'] ?? null,
            dueDateFrom: $this->parseDate($filters['due_date_from'] ?? null),
            dueDateTo: $this->parseDate($filters['due_date_to'] ?? null)?->setTime(23, 59, 59),
            overdueOnly: $overdueOnly,
        );

        $tasks = $this->taskRepository->findByBandSpace($bandSpace, $taskFilter);

        $taskIds = array_map(fn(Task $task): string => (string) $task->id, $tasks);
        $commentCounts = $this->taskCommentRepository->countByTaskIds($taskIds);
        $fileCounts = $this->fileAttachmentRepository->countActiveBySourceIds('task', $taskIds);

        return $this->taskBuilder->buildFromList($tasks, $commentCounts, $fileCounts);
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? null : $date;
    }
}
