<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageCreate;
use App\ApiResource\BandSpace\Task\TaskCreate;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Task;
use App\Entity\Message\Message;
use App\Entity\Message\MessageAttachment;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Procedure\BandSpace\TaskCreateProcedure;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\ChatMessageTaskSeed;
use App\Service\Builder\BandSpace\TaskBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turning a chat message into a task (#979).
 *
 * Any active member, not only the author and not only an admin: the member who reads « faut penser au
 * câble XLR » is the one who knows it will be forgotten, and they are rarely the one who wrote it.
 * Nothing of the message is changed, so there is no authorship rule to apply.
 *
 * @implements ProcessorInterface<mixed, TaskResource>
 */
readonly class ChatMessageTaskCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private MessageAttachmentRepository $messageAttachmentRepository,
        private ChatMessageTaskSeed $chatMessageTaskSeed,
        private TaskCreateProcedure $taskCreateProcedure,
        private TaskBuilder $taskBuilder,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        // A tombstone makes no task, the same 404 the edit, delete and pin endpoints answer with:
        // its content is gone, so there is nothing left to turn into anything (#967).
        if (!$message instanceof Message || $message->isDeleted()) {
            throw new NotFoundHttpException('Message introuvable');
        }

        $this->assertRoomForOneMoreCard($message);

        $input = $this->chatMessageTaskSeed->fromMessage($message);
        $task = $this->entityManager->wrapInTransaction(
            fn (): Task => $this->createAndLink($input, $bandSpace, $message, $user),
        );

        return $this->taskBuilder->buildItem($task, linkedMessageId: (string) $message->id);
    }

    /**
     * The task and the row tying it to the message are written together: the link is the whole point
     * of the feature, so a task arriving without one would be a silent half-failure, and retrying it
     * would leave the band with two tasks saying the same thing.
     */
    private function createAndLink(TaskCreate $input, BandSpace $bandSpace, Message $message, User $user): Task
    {
        $task = $this->taskCreateProcedure->create($input, $bandSpace, $user);
        // The same row a picker-created attachment writes, label included: the label is a snapshot of
        // the target's title, and the title here is the one the task was just given.
        $this->entityManager->persist(
            new MessageAttachment($message, BandSpaceSearchResultType::Task, (string) $task->id, $task->title),
        );
        $this->entityManager->flush();

        return $task;
    }

    /**
     * The cap a message is written under applies to a card added afterwards too, and for the same
     * reason: past a handful the bubble stops being a message and becomes a list, which the board
     * itself does better. A member who has hit it can still create the task from the board.
     *
     * Counted and written without a lock, the same call ChatMessagePinProcessor makes about its own
     * cap: two members clicking at once can take a message to six cards, which costs one extra line
     * under a bubble the band controls anyway, and taking a write lock on the message on every call
     * to prevent it would be the more expensive mistake. Re-counting inside the transaction would
     * not close it either, since REPEATABLE READ hides the other transaction's uncommitted row.
     */
    private function assertRoomForOneMoreCard(Message $message): void
    {
        if ($this->messageAttachmentRepository->countByMessage($message) >= ChatMessageCreate::MAX_ATTACHMENTS) {
            throw new ConflictHttpException(sprintf(
                'Ce message référence déjà %d éléments, il ne peut pas en porter davantage.',
                ChatMessageCreate::MAX_ATTACHMENTS,
            ));
        }
    }
}
