<?php

declare(strict_types=1);

namespace App\State\Provider\BandSpace\Task;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskStats;
use App\Entity\User;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<TaskStats>
 */
readonly class TaskStatsProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TaskStats
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $counts = $this->taskRepository->getStatusCounts($bandSpace, new \DateTimeImmutable());

        $stats = new TaskStats();
        $stats->bandSpaceId = (string) $bandSpace->id;
        $stats->todo = $counts['todo'];
        $stats->inProgress = $counts['in_progress'];
        $stats->done = $counts['done'];
        $stats->overdue = $counts['overdue'];

        return $stats;
    }
}
