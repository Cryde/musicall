<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\User;
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
     * The content is built here, in each recipient's own locale (#1191), once per locale among them.
     *
     * @param iterable<User>                $recipients
     * @param \Closure(string): PushContent $contentIn builds the push in the locale it is given
     */
    public function queue(iterable $recipients, \Closure $contentIn, ?PushCategory $category): void
    {
        $recipientIdsByLocale = [];
        foreach ($recipients as $recipient) {
            $recipientIdsByLocale[$recipient->locale][] = $recipient->id;
        }

        foreach ($recipientIdsByLocale as $locale => $userIds) {
            $locale = (string) $locale;
            try {
                $content = $contentIn($locale);
            } catch (\Throwable $throwable) {
                // Logged once for the locale; the other locales still go out.
                $this->logger->error('Could not build a push notification', [
                    'exception' => $throwable,
                    'locale' => $locale,
                    'user_count' => count($userIds),
                ]);
                continue;
            }

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
}
