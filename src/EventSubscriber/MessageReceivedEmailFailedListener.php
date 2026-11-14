<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Messenger\MessageReceivedEmailStamp;
use App\Repository\Message\MessageThreadMetaRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Releases a thread's email throttle once its "message received" email has failed for good in the
 * worker (#1156), as MessageSentListener does for a dispatch that fails in the request (#1151).
 * Default priority on purpose: willRetry() is only set by Messenger's retry listener, at priority 100.
 */
#[AsEventListener]
readonly class MessageReceivedEmailFailedListener
{
    public function __construct(
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private LoggerInterface             $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $stamp = $event->getEnvelope()->last(MessageReceivedEmailStamp::class);
        if ($event->willRetry() || !$stamp instanceof MessageReceivedEmailStamp) {
            return;
        }

        try {
            $this->messageThreadMetaRepository->releasePendingNotification($stamp->threadId, $stamp->recipientId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to release the message notification throttle', [
                'thread_id' => $stamp->threadId,
                'recipient_id' => $stamp->recipientId,
                'exception' => $e,
            ]);
        }
    }
}
