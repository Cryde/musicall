<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Message\Message;
use App\Event\MessagePostedEvent;
use App\Mercure\MercureTopic;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells every participant's browser that the thread changed.
 */
#[AsEventListener]
readonly class MessagePostedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MessagePostedEvent $event): void
    {
        $message = $event->message;
        $topics = [];
        foreach ($message->thread->messageParticipants as $participant) {
            // The sender included, so a send from their laptop still updates their phone. Their own
            // tab pays one idempotent refetch for that.
            $topics[] = MercureTopic::userNotifications((string) $participant->participant->id);
        }

        if ($topics === []) {
            return;
        }

        try {
            // A tag, not the message. The browser refetches through the API it already uses, so no
            // message content ever travels this way and nothing here becomes a second place that
            // renders it. `private` is the whole of the authorization: the hub consults subscriber
            // topic selectors only for private updates.
            $this->hub->publish(new Update($topics, self::tagFor($message), private: true));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a message signal, the inbox will update on its next load', [
                'exception' => $throwable,
                'thread_id' => (string) $message->thread->id,
            ]);
        }
    }

    /**
     * The thread id is here so a browser can tell the conversation it is looking at from any other and
     * skip refetching messages it is not showing.
     */
    private static function tagFor(Message $message): string
    {
        return json_encode(
            ['type' => 'message', 'thread_id' => (string) $message->thread->id],
            JSON_THROW_ON_ERROR,
        );
    }
}
