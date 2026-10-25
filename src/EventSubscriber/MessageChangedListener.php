<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageChangedEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells both people in a direct conversation that a message on screen changed (#1245), the band
 * chat's BandSpaceChatMessageChangedListener for two. The actor is included, so their other devices
 * follow.
 */
#[AsEventListener]
readonly class MessageChangedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(MessageChangedEvent $event): void
    {
        $message = $event->message;
        if ($message->thread->isChannel()) {
            return;
        }

        try {
            $topics = [];
            foreach ($this->threadMemberResolver->activeMembersOf($message->thread) as $participant) {
                // A direct thread keeps a deleted account among its participants, and nobody reads for it.
                if (!$participant->isDeleted()) {
                    $topics[] = MercureTopic::userNotifications((string) $participant->id);
                }
            }

            if ($topics === []) {
                return;
            }

            // A tag, never the message: the browser re-reads it through the API.
            $this->hub->publish(new Update(
                $topics,
                json_encode([
                    'type' => 'message_changed',
                    'thread_id' => (string) $message->thread->id,
                    'message_id' => (string) $message->id,
                    'change' => $event->change->value,
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a message change signal, the conversation will update on its next load', [
                'exception' => $throwable,
                'message_id' => (string) $message->id,
            ]);
        }
    }
}
