<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Event\MessagePostedEvent;
use App\Mercure\MercureTopic;
use App\Service\Message\ThreadMemberResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Tells everybody in the conversation that it changed, whether it is a direct message or a Band
 * Space channel.
 *
 * **One topic per recipient, each of them private**, rather than a single shared channel topic
 * (#963). Both are one HTTP POST to the hub, since Hub::publish() sends the whole topic list as one
 * form field, so the choice costs nothing either way at send time. What a shared topic would cost is
 * on the subscriber side: the token would have to enumerate every space a member belongs to, so it
 * would need reissuing on invite accept, leave, kick and account deletion, and until one of those
 * happened a former member would keep receiving. Listing the recipients here instead means the list
 * is computed from BandSpaceMembership at publish time, so somebody who left or was kicked is gone
 * from the very next message, with no token to expire. The subscriber cookie stays the single topic
 * #949 issued.
 */
#[AsEventListener]
readonly class MessagePostedListener
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private ThreadMemberResolver $threadMemberResolver,
    ) {
    }

    public function __invoke(MessagePostedEvent $event): void
    {
        $message = $event->message;
        $topics = [];
        // The resolver, not the thread's participant rows: a channel has none, so iterating them
        // published nothing at all for a band (#959). It answers both shapes, and for a direct
        // message it answers with those same rows.
        foreach ($this->threadMemberResolver->activeMembersOf($message->thread) as $member) {
            // The sender included, so a send from their laptop still updates their phone. Their own
            // tab pays one idempotent refetch for that.
            $topics[] = MercureTopic::userNotifications((string) $member->id);
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
     * Which conversation moved, so a browser can skip refetching one it is not showing.
     *
     * Two types rather than one with an extra field, because the client routes on the type: only a
     * channel reaches the band space tab, and only a channel needs the space id, which is what the
     * chat API takes. A channel carries **both** ids since #994, because the inbox now lists it too
     * and keys what it is showing by thread. #1013 is what makes the thread id load bearing rather
     * than merely convenient, once one space has several channels.
     */
    private static function tagFor(Message $message): string
    {
        $thread = $message->thread;
        $tag = $thread->bandSpace instanceof BandSpace
            ? [
                'type' => 'band_space_message',
                'band_space_id' => (string) $thread->bandSpace->id,
                'thread_id' => (string) $thread->id,
            ]
            : ['type' => 'message', 'thread_id' => (string) $thread->id];

        return json_encode($tag, JSON_THROW_ON_ERROR);
    }
}
