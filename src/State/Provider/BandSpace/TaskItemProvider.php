<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskItemProvider implements ProviderInterface
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

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$task instanceof \App\Entity\BandSpace\Task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $taskId = (string) $task->id;
        $commentCounts = $this->taskCommentRepository->countByTaskIds([$taskId]);
        $fileCounts = $this->fileAttachmentRepository->countActiveBySourceIds('task', [$taskId]);

        return $this->taskBuilder->buildItem(
            $task,
            $commentCounts[$taskId] ?? 0,
            $fileCounts[$taskId] ?? 0,
        );
    }
}
