<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageThreadReadEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ReadReceiptPolicy;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells the other participant of a direct conversation that their message was read, so « Vu » shows
 * live (#1243). A tag with no position in it: their tab refetches the page through the API. Nobody
 * hears it when the receipt does not apply between the two, so a signal never says more than the page.
 */
#[AsEventListener]
readonly class MessageThreadReadListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
        private ReadReceiptPolicy $readReceiptPolicy,
    ) {
    }

    public function __invoke(MessageThreadReadEvent $event): void
    {
        // The whole body, lookups included: a receipt never fails the read that caused it.
        try {
            $readerId = (string) $event->reader->id;
            $topics = [];
            foreach ($this->threadMemberResolver->activeMembersOf($event->thread) as $participant) {
                if ((string) $participant->id !== $readerId
                    && $this->readReceiptPolicy->appliesBetween($event->reader, $participant)
                ) {
                    $topics[] = MercureTopic::userNotifications((string) $participant->id);
                }
            }

            if ($topics === []) {
                return;
            }

            $this->hub->publish(new Update(
                $topics,
                json_encode(['type' => 'message_read', 'thread_id' => (string) $event->thread->id], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not publish a message read signal, « Vu » will update on the next load', [
                'exception' => $throwable,
                'thread_id' => (string) $event->thread->id,
            ]);
        }
    }
}
