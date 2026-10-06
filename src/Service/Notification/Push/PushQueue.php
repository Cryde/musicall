<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Enum\Notification\PushCategory;
use App\Messenger\SendPushNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Queues pushes for the worker (#1110), one per recipient. Never throws: what caused the push is
 * already committed, and a push is a side channel it must not fail for.
 */
readonly class PushQueue
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param iterable<string> $userIds
     */
    public function queue(iterable $userIds, PushContent $content, ?PushCategory $category): void
    {
        foreach ($userIds as $userId) {
            try {
                $this->messageBus->dispatch(new SendPushNotification($userId, $content->title, $content->body, $content->data, $category));
            } catch (\Throwable $throwable) {
                $this->logger->error('Could not queue a push notification', [
                    'exception' => $throwable,
                    'user_id' => $userId,
                    'type' => $content->data['type'] ?? null,
                ]);
            }
        }
    }
}
