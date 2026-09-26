<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\BandSpaceChatPresenceChangedEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the rest of the band that who is online changed (#1040), so their open chats ask again now
 * rather than at their next heartbeat. A tag with no member in it: the answer to « who » comes back
 * through the heartbeat, which applies everybody's opt-out.
 */
#[AsEventListener]
readonly class BandSpaceChatPresenceChangedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(BandSpaceChatPresenceChangedEvent $event): void
    {
        $memberId = (string) $event->member->id;
        $topics = [];
        foreach ($this->threadMemberResolver->activeMembersOf($event->channel) as $other) {
            if ((string) $other->id !== $memberId) {
                $topics[] = MercureTopic::userNotifications((string) $other->id);
            }
        }

        if ($topics === []) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                $topics,
                json_encode(['type' => 'band_space_presence', 'band_space_id' => (string) $event->bandSpace->id], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->warning('Could not publish a presence signal, open chats will catch up on their next heartbeat', [
                'exception' => $throwable,
                'band_space_id' => (string) $event->bandSpace->id,
            ]);
        }
    }
}
