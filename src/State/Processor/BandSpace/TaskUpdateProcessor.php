<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\User;
use App\Procedure\BandSpace\TaskUpdateProcedure;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskResource, TaskResource>
 */
readonly class TaskUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private TaskCommentRepository $taskCommentRepository,
        private BandSpaceFileAttachmentRepository $fileAttachmentRepository,
        private TaskUpdateProcedure $taskUpdateProcedure,
        private TaskBuilder $taskBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param TaskResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $task = $this->taskUpdateProcedure->update($task, $payload, $data, $bandSpace, $user);

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
