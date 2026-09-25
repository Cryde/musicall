<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\Chat\ChatImageStore;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        private BandSpaceFileRepository $bandSpaceFileRepository,
        private ChatImageStore $chatImageStore,
        private LoggerInterface $logger,
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
        // And out of the « infos importantes » bar, in the same flush: what was worth keeping at the
        // top is exactly what has just gone (#969).
        $message->pinnedDatetime = null;
        $message->pinnedBy = null;
        $imageFileId = $message->imageFileId;
        $message->imageFileId = null;
        $this->entityManager->flush();

        $this->messageMentionRepository->deleteByMessage($message);
        // And what it pointed at (#970). An attachment names a task or a file, so leaving the rows
        // would keep serving that in the payload of a message whose content is supposed to be gone.
        $this->messageAttachmentRepository->deleteByMessage($message);
        // And its image, for good (#973): the message is what it was posted in, so it goes with it.
        // Already purged from the Files trash is fine, there is simply nothing left to remove.
        $image = $imageFileId !== null ? $this->bandSpaceFileRepository->findOneByIdAndBandSpace((string) $imageFileId, $bandSpace) : null;
        if (!$image instanceof BandSpaceFile) {
            return;
        }
        // Best-effort: the tombstone is committed, so the delete has happened as far as anybody can
        // see, and a failure here must not report it as failed.
        try {
            $this->chatImageStore->discard($image);
        } catch (\Throwable $e) {
            $this->logger->error('Could not purge the image of a deleted chat message', [
                'message_id' => (string) $message->id,
                'file_id' => (string) $image->id,
                'exception' => $e,
            ]);
        }
    }
}
