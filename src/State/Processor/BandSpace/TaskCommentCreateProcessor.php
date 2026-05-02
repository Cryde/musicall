<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCommentCreate;
use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
use App\Repository\BandSpace\TaskRepository;
use App\Repository\UserRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\MentionParserService;
use App\Service\BandSpace\TaskActivityRecorder;
use App\Service\Builder\BandSpace\TaskCommentBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCommentCreate, TaskCommentResource>
 */
readonly class TaskCommentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private MentionParserService $mentionParserService,
        private TaskActivityRecorder $taskActivityRecorder,
        private TaskCommentBuilder $taskCommentBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCommentCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskCommentResource
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

        $comment = new TaskComment();
        $comment->task = $task;
        $comment->author = $user;
        $comment->content = $data->content;

        $this->entityManager->persist($comment);

        $this->taskActivityRecorder->record($task, $user, 'comment_added');

        $mentionedUuids = $this->mentionParserService->extractMentions($data->content);
        $mentionedMembers = $this->userRepository->findActiveBandSpaceMembersByIds($bandSpace, $mentionedUuids);
        foreach ($mentionedMembers as $mentionedUser) {
            $this->taskActivityRecorder->record($task, $user, 'mention', [
                'mentioned_user_id' => $mentionedUser->id,
                'mentioned_username' => $mentionedUser->username,
            ]);
        }

        $this->entityManager->flush();

        return $this->taskCommentBuilder->buildItem($comment);
    }
}
