<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\Notification\PushCategory;
use App\Event\MessagePostedEvent;
use App\Mercure\MercureTopic;
use App\Service\BandSpace\BandSpaceMemberNames;
use App\Service\BandSpace\ChatMentionResolver;
use App\Service\Message\ThreadMemberResolver;
use App\Service\Notification\Push\PushContentBuilder;
use App\Service\Notification\Push\PushQueue;
use App\Service\User\UserNotificationPreferenceChecker;
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
        private ChatMentionResolver $chatMentionResolver,
        private BandSpaceMemberNames $memberNames,
        private PushContentBuilder $pushContentBuilder,
        private PushQueue $pushQueue,
        private UserNotificationPreferenceChecker $preferenceChecker,
    ) {
    }

    public function __invoke(MessagePostedEvent $event): void
    {
        $message = $event->message;
        $topics = [];
        $members = [];
        // The resolver, not the thread's participant rows: a channel has none, so iterating them
        // published nothing at all for a band (#959). It answers both shapes, and for a direct
        // message it answers with those same rows.
        foreach ($this->threadMemberResolver->activeMembersOf($message->thread) as $member) {
            // The sender included, so a send from their laptop still updates their phone. Their own
            // tab pays one idempotent refetch for that.
            $topics[] = MercureTopic::userNotifications((string) $member->id);
            $members[] = $member;
        }

        if ($topics === []) {
            return;
        }

        $this->push($message, $members);

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
     * Every other member's phones (#1110), each message, with a preview of it. In a channel, somebody
     * the message mentions is left out when the mention push reaches them: two pushes for one message
     * is one too many. Never throws, for the same reason as the signal below.
     *
     * The mentions are resolved here rather than read back: this runs before the chat processor
     * records them.
     *
     * @param list<User> $members
     */
    private function push(Message $message, array $members): void
    {
        try {
            $bandSpace = $message->thread->bandSpace;
            $mentioned = $bandSpace instanceof BandSpace ? $this->chatMentionResolver->resolve($bandSpace, $message->content) : [];
            $mentionUsernamesById = [];
            foreach ($mentioned as $user) {
                $mentionUsernamesById[(string) $user->id] = $user->username;
            }

            // A mentioned member whose mention pushes are on gets that push instead; one who turned
            // them off still gets the message as a chat push.
            $coveredByMention = [];
            foreach ($mentioned as $user) {
                if ($this->preferenceChecker->canReceivePush($user, PushCategory::BandMention)) {
                    $coveredByMention[(string) $user->id] = true;
                }
            }

            $authorId = (string) $message->author->id;
            $recipientIds = [];
            foreach ($members as $member) {
                $memberId = (string) $member->id;
                if ($memberId !== $authorId && !isset($coveredByMention[$memberId])) {
                    $recipientIds[] = $memberId;
                }
            }

            $this->pushQueue->queue(
                $recipientIds,
                $this->pushContentBuilder->forMessage($message, $this->authorName($message), $mentionUsernamesById),
                $bandSpace instanceof BandSpace ? PushCategory::BandChat : PushCategory::MessageReceived,
            );
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not queue the pushes for a message', [
                'exception' => $throwable,
                'message_id' => (string) $message->id,
            ]);
        }
    }

    /** The name the conversation shows: the stage name inside a band (#1115), the public one elsewhere. */
    private function authorName(Message $message): string
    {
        $bandSpace = $message->thread->bandSpace;

        return $bandSpace instanceof BandSpace
            ? $this->memberNames->nameOf($message->author, (string) $bandSpace->id)
            : $message->author->publicName();
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
