<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageThreadTypingEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * « X est en train d'écrire » to the other participant of a direct conversation (#1242). Its own type,
 * so the band chat's `band_space_typing` handlers never pick it up.
 */
#[AsEventListener]
readonly class MessageThreadTypingListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(MessageThreadTypingEvent $event): void
    {
        $typistId = (string) $event->typist->id;
        $topics = [];
        foreach ($this->threadMemberResolver->activeMembersOf($event->thread) as $participant) {
            // A direct thread keeps a deleted account among its participants, and nobody reads for it.
            if ((string) $participant->id !== $typistId && !$participant->isDeleted()) {
                $topics[] = MercureTopic::userNotifications((string) $participant->id);
            }
        }

        if ($topics === []) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                $topics,
                json_encode([
                    'type' => 'message_typing',
                    'thread_id' => (string) $event->thread->id,
                    'user_id' => $typistId,
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not publish a typing signal', [
                'exception' => $throwable,
                'thread_id' => (string) $event->thread->id,
            ]);
        }
    }
}
