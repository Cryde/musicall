<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpace;
use App\Event\BandSpaceChatMessageChangedEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the band that a message on screen changed (#1056), on the same private per-member topics as a
 * new message, see MessagePostedListener for why one topic per member.
 *
 * Its own type rather than `band_space_message`: a new message moves the unread badge and marks the
 * chat read, a reaction or an edit must do neither. The actor is included, so their other devices
 * follow; their own tab pays one idempotent refetch.
 */
#[AsEventListener]
readonly class BandSpaceChatMessageChangedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(BandSpaceChatMessageChangedEvent $event): void
    {
        $message = $event->message;
        $bandSpace = $message->thread->bandSpace;
        if (!$bandSpace instanceof BandSpace) {
            return;
        }

        $topics = [];
        foreach ($this->threadMemberResolver->activeMembersOf($message->thread) as $member) {
            $topics[] = MercureTopic::userNotifications((string) $member->id);
        }

        if ($topics === []) {
            return;
        }

        try {
            // A tag, never the message: the browser re-reads it through the API.
            $this->hub->publish(new Update(
                $topics,
                json_encode([
                    'type' => 'band_space_message_changed',
                    'band_space_id' => (string) $bandSpace->id,
                    'thread_id' => (string) $message->thread->id,
                    'message_id' => (string) $message->id,
                    'change' => $event->change->value,
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a message change signal, the chat will update on its next load', [
                'exception' => $throwable,
                'message_id' => (string) $message->id,
            ]);
        }
    }
}
