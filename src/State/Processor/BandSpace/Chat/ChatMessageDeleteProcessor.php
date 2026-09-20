<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deleting a chat message: the author's own, or anybody's for an administrator (#967).
 *
 * The same rule as TaskCommentDeleteProcessor, but not the same act. That one removes the row; this
 * one leaves a tombstone, because a hole in a conversation reads as a bug and because
 * message_thread.last_message_id points here. What goes is the content itself, emptied on the row and
 * stripped of its mentions, so an administrator taking down an abusive message really takes it down.
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class ChatMessageDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private MessageMentionRepository $messageMentionRepository,
        private MessageAttachmentRepository $messageAttachmentRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        // An already deleted message is gone as far as this endpoint is concerned, so deleting it again
        // is a miss rather than a second delete. Answered before the author check on purpose: every
        // member already sees the tombstone in the list, so there is nothing for a 403 to protect.
        if (!$message instanceof Message || $message->isDeleted()) {
            throw new NotFoundHttpException('Message introuvable');
        }

        if ($message->author->id !== $user->id && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul l\'auteur ou un administrateur peut supprimer ce message');
        }

        // Two commits, not one: the mention delete is a DQL statement with a transaction of its own.
        // The tombstone therefore goes first, because that is the order that fails safely. The other
        // way round, a flush that threw would leave the text on screen for everybody with its mentions
        // already gone; this way it leaves the message untouched, and a failure of the second
        // statement leaves rows pointing into a content that is already empty and renders nothing.
        $message->deletionDatetime = new \DateTimeImmutable();
        $message->content = '';
        $this->entityManager->flush();

        $this->messageMentionRepository->deleteByMessage($message);
        // And what it pointed at (#970). An attachment names a task or a file, so leaving the rows
        // would keep serving that in the payload of a message whose content is supposed to be gone.
        $this->messageAttachmentRepository->deleteByMessage($message);
    }
}
