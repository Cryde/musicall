<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Event\MessageThreadReadEvent;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use App\Service\User\UserNotificationPreferenceChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<MessageThreadMetaResource, MessageThreadMetaResource>
 */
readonly class MessageThreadMetaPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private Security                    $security,
        private EntityManagerInterface      $entityManager,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageThreadMetaBuilder    $messageThreadMetaBuilder,
        private MessageRepository           $messageRepository,
        private EventDispatcherInterface    $eventDispatcher,
        private UserNotificationPreferenceChecker $preferenceChecker,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): MessageThreadMetaResource
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('common.not_authenticated');
        }
        /** @var User $user */
        $user = $this->security->getUser();

        $entity = $this->messageThreadMetaRepository->find($uriVariables['id']);
        if (!$entity instanceof MessageThreadMeta) {
            throw new NotFoundHttpException('message.thread_meta.not_found');
        }
        // Unreachable while MessageThreadMetaItemProvider guards the same operation, and kept anyway:
        // this lookup is its own, so a future operation wired to a different provider still lands here.
        if ($entity->user->id !== $user->id) {
            throw new AccessDeniedException('message.thread_meta.update_forbidden');
        }

        // When the recipient catches up on the thread, reset the
        // one-email-per-unread-streak flag so the next incoming message can
        // trigger another notification (#533). The trigger is unchanged by #954,
        // only how "was it unread" is asked: there is something unread when the
        // count is not zero, which is what the boolean used to stand for.
        if ($data->isRead === true && $entity->hasUnread()) {
            $entity->pendingNotificationSent = false;
        }

        // Asked first, since afterwards the position is `now`. Only a read covering the other person's
        // message changes their « Vu » (#1243). A channel opened from the inbox lands here too and has
        // its own receipt signal, so it is left out.
        // Somebody hiding that they read leaves no seen position to reveal once they stop hiding it.
        $recordsSeen = $this->preferenceChecker->showsReadReceipts($user);
        $changesReceipt = $data->isRead === true
            && $recordsSeen
            && !$entity->thread->isChannel()
            && $this->messageRepository->hasThreadMessageUnseenBy($entity->thread, $user);

        // A command, not a state: mark read as of now, or put the read position back to nothing, which
        // leaves the seen position where it was.
        if ($data->isRead === true) {
            $entity->markReadAt(new \DateTimeImmutable(), $recordsSeen);
        } else {
            $entity->lastReadDatetime = null;
        }
        $this->entityManager->flush();

        if ($changesReceipt) {
            $this->eventDispatcher->dispatch(new MessageThreadReadEvent($entity->thread, $user));
        }

        return $this->messageThreadMetaBuilder->buildItem($entity);
    }
}
