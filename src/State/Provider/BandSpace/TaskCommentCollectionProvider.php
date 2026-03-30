<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\User;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskCommentBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskCommentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private TaskCommentRepository $taskCommentRepository,
        private TaskCommentBuilder $taskCommentBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return TaskCommentResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['taskId'], $bandSpace);
        if (!$task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $comments = $this->taskCommentRepository->findByTask($task);

        return $this->taskCommentBuilder->buildFromList($comments);
    }
}
