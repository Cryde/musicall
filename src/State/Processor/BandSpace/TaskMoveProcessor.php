<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskMove;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\User;
use App\Procedure\BandSpace\TaskMoveProcedure;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TaskMove, TaskResource>
 */
readonly class TaskMoveProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskMoveProcedure $taskMoveProcedure,
        private TaskCommentRepository $taskCommentRepository,
        private BandSpaceFileAttachmentRepository $fileAttachmentRepository,
        private TaskBuilder $taskBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskMove $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        /** @var array<int, array{id: string, position: int}> $positions */
        $positions = $data->positions;

        $task = $this->taskMoveProcedure->move(
            $bandSpace,
            $data->taskId,
            $data->status,
            $positions,
            $user,
        );

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
