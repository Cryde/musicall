<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCommentCreate;
use App\ApiResource\BandSpace\Task\TaskCommentResource;
use App\Entity\BandSpace\TaskComment;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use App\Event\BandSpaceTaskCommentedEvent;
use App\Event\BandSpaceTaskMentionedEvent;
use App\Repository\BandSpace\TaskRepository;
use App\Repository\UserRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\MentionParserService;
use App\Service\Builder\BandSpace\TaskCommentBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private TaskCommentBuilder $taskCommentBuilder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
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
        if (!$task instanceof \App\Entity\BandSpace\Task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $comment = new TaskComment();
        $comment->task = $task;
        $comment->author = $user;
        $comment->content = $data->content;

        $this->entityManager->persist($comment);

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $task->bandSpace,
            module: BandSpaceModule::Task,
            type: BandSpaceTaskActivityType::CommentAdded,
            resourceId: $task->id,
            actor: $user,
        );

        $mentionedUuids = $this->mentionParserService->extractMentions($data->content);
        $mentionedMembers = $this->userRepository->findActiveBandSpaceMembersByIds($bandSpace, $mentionedUuids);
        foreach ($mentionedMembers as $mentionedUser) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $task->bandSpace,
                module: BandSpaceModule::Task,
                type: BandSpaceTaskActivityType::Mention,
                resourceId: $task->id,
                actor: $user,
                payload: [
                    'mentioned_user_id' => $mentionedUser->id,
                    'mentioned_username' => $mentionedUser->username,
                ],
            );
        }

        $this->entityManager->flush();

        // Best-effort notifications dispatched after the commit (epic #689 contract).
        // Mentions notify the @-mentioned members; the participant fan-out notifies the task's
        // creator/assignees/prior commenters, excluding anyone already covered by a mention.
        if ($mentionedMembers !== []) {
            $this->eventDispatcher->dispatch(new BandSpaceTaskMentionedEvent($comment, $mentionedMembers));
        }
        $this->eventDispatcher->dispatch(new BandSpaceTaskCommentedEvent($comment, $mentionedMembers));

        return $this->taskCommentBuilder->buildItem($comment);
    }
}
