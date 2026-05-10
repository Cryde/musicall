<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\TaskCommentBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCommentResource, TaskCommentResource>
 */
readonly class TaskCommentUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private TaskCommentRepository $taskCommentRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private TaskCommentBuilder $taskCommentBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCommentResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskCommentResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['taskId'], $bandSpace);
        if (!$task instanceof \App\Entity\BandSpace\Task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $comment = $this->taskCommentRepository->findOneByIdAndTask((string) $uriVariables['id'], $task);
        if (!$comment instanceof \App\Entity\BandSpace\TaskComment) {
            throw new NotFoundHttpException('Commentaire introuvable');
        }

        if ($comment->author->id !== $user->id) {
            throw new AccessDeniedHttpException('Seul l\'auteur peut modifier ce commentaire');
        }

        $comment->content = $data->content;
        $comment->updateDatetime = new DateTime();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $task->bandSpace,
            module: BandSpaceModule::Task,
            type: BandSpaceTaskActivityType::CommentEdited,
            resourceId: $task->id,
            actor: $user,
            payload: ['comment_id' => (string) $comment->id],
        );

        $this->entityManager->flush();

        return $this->taskCommentBuilder->buildItem($comment);
    }
}
