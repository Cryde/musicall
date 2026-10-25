<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\Message\MessageChange;
use App\Event\MessageChangedEvent;
use App\Repository\Message\MessageFileRepository;
use App\Security\Message\DirectThreadChecker;
use App\Service\Message\DirectMessageMediaStore;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deleting your own message in a direct conversation (#1245). Author only, since a conversation has
 * no admin. A tombstone rather than a removal, like the band chat (#967): a hole reads as a bug, and
 * message_thread.last_message_id may point here. The content goes, emptied on the row, and its image
 * or voice note with it (#1247).
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class MessageDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private Security $security,
        private MessageFileRepository $messageFileRepository,
        private DirectMessageMediaStore $mediaStore,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$thread, $message] = $this->directThreadChecker->checkMessageForWrite(
            (string) $uriVariables['threadId'],
            (string) $uriVariables['id'],
            $user,
        );
        // Deleting again is a miss, answered before the author check: both people already see the
        // tombstone, so there is nothing for a 403 to protect.
        if ($message->isDeleted()) {
            throw new NotFoundHttpException('message.message.not_found');
        }
        if ($message->author->id !== $user->id) {
            throw new AccessDeniedHttpException('message.message.delete_forbidden');
        }

        $mediaIds = array_values(array_filter([$message->imageFileId, $message->voiceNoteFileId]));
        $message->deletionDatetime = new \DateTimeImmutable();
        $message->content = '';
        $message->imageFileId = null;
        $message->voiceNoteFileId = null;
        $message->voiceNoteDurationSeconds = null;
        $message->voiceNotePeaks = null;
        $this->entityManager->flush();

        // After the tombstone is written: a file that cannot be removed now is an orphan to log, never
        // a reason to fail a delete the author has already seen succeed.
        foreach ($mediaIds as $mediaId) {
            $this->discardQuietly($mediaId, $thread);
        }

        $this->eventDispatcher->dispatch(new MessageChangedEvent($message, MessageChange::Delete));
    }

    private function discardQuietly(string $mediaId, MessageThread $thread): void
    {
        try {
            $file = $this->messageFileRepository->findOneInThread($mediaId, $thread);
            if ($file instanceof MessageFile) {
                $this->mediaStore->discard($file);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Could not remove the media of a deleted direct message', ['file_id' => $mediaId, 'exception' => $e]);
        }
    }
}
