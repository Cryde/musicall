<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpace;
use App\Event\ChatPresenceChangedEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the others in a conversation that who is online changed (#1040, #1246), so their open chats
 * ask again now rather than at their next heartbeat. A tag with no member in it: the answer to « who »
 * comes back through the heartbeat, which applies everybody's opt-out.
 */
#[AsEventListener]
readonly class ChatPresenceChangedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(ChatPresenceChangedEvent $event): void
    {
        try {
            $memberId = (string) $event->member->id;
            $topics = [];
            foreach ($this->threadMemberResolver->activeMembersOf($event->thread) as $other) {
                // A direct thread keeps a deleted account among its participants, and nobody reads for it.
                if ((string) $other->id !== $memberId && !$other->isDeleted()) {
                    $topics[] = MercureTopic::userNotifications((string) $other->id);
                }
            }

            if ($topics === []) {
                return;
            }

            // Each client routes its own type: the band chat by space, the inbox by thread.
            $bandSpace = $event->thread->bandSpace;
            $payload = $bandSpace instanceof BandSpace
                ? ['type' => 'band_space_presence', 'band_space_id' => (string) $bandSpace->id]
                : ['type' => 'message_presence', 'thread_id' => (string) $event->thread->id];

            $this->hub->publish(new Update($topics, json_encode($payload, JSON_THROW_ON_ERROR), private: true));
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not publish a presence signal, open chats will catch up on their next heartbeat', [
                'exception' => $throwable,
                'thread_id' => (string) $event->thread->id,
            ]);
        }
    }
}
