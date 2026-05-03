<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\TaskActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCommentResource, void>
 */
readonly class TaskCommentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private TaskCommentRepository $taskCommentRepository,
        private TaskActivityRecorder $taskActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCommentResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['taskId'], $membership->bandSpace);
        if (!$task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $comment = $this->taskCommentRepository->findOneByIdAndTask((string) $uriVariables['id'], $task);
        if (!$comment) {
            throw new NotFoundHttpException('Commentaire introuvable');
        }

        $isAuthor = $comment->author->id === $user->id;
        if (!$isAuthor && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul l\'auteur ou un administrateur peut supprimer ce commentaire');
        }

        $this->taskActivityRecorder->record($task, $user, 'comment_deleted', [
            'comment_id' => (string) $comment->id,
        ]);

        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}
