<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\BandSpaceChatTypingEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * « X écrit… » to the rest of the band (#1040), on the same private per-member topics as a new
 * message. Not to the typist: their own tab knows. The recipients are the roster at publish time, so
 * somebody who left stops seeing it from the next keystroke.
 *
 * Unlike a message signal this one carries the typist's id, because there is nothing to refetch: who
 * is writing is the whole of it, and an id is not content.
 */
#[AsEventListener]
readonly class BandSpaceChatTypingListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(BandSpaceChatTypingEvent $event): void
    {
        $typistId = (string) $event->typist->id;
        $topics = [];
        foreach ($this->threadMemberResolver->activeMembersOf($event->channel) as $member) {
            if ((string) $member->id !== $typistId) {
                $topics[] = MercureTopic::userNotifications((string) $member->id);
            }
        }

        if ($topics === []) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                $topics,
                json_encode([
                    'type' => 'band_space_typing',
                    'band_space_id' => (string) $event->bandSpace->id,
                    'thread_id' => (string) $event->channel->id,
                    'user_id' => $typistId,
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            // A typing indicator is a nicety: a hub that is down costs nobody anything they wrote.
            $this->logger->warning('Could not publish a typing signal', [
                'exception' => $throwable,
                'band_space_id' => (string) $event->bandSpace->id,
            ]);
        }
    }
}
