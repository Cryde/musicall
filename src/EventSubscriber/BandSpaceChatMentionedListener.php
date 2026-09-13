<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Event\BandSpaceChatMentionedEvent;
use App\Service\Notification\NotificationCreator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifies the members named with an `@` in a band's chat (#964).
 *
 * A mention is the **only** thing in the chat that writes a Notification row: an ordinary message
 * moves an unread count and nothing more, which is concern 10 of #948. Best-effort per the #689
 * resilience contract, so the event arrives after the message is committed and anything thrown here
 * is swallowed and logged rather than allowed to 500 a message that has already been sent.
 *
 * The author is excluded: mentioning yourself, which `@tous` does to whoever sends it, must not
 * notify you.
 */
#[AsEventListener]
readonly class BandSpaceChatMentionedListener
{
    public function __construct(
        private NotificationCreator $notificationCreator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BandSpaceChatMentionedEvent $event): void
    {
        $message = $event->message;
        $actorId = (string) $message->author->id;
        $recipients = array_filter(
            $event->mentionedUsers,
            static fn (User $user): bool => (string) $user->id !== $actorId,
        );

        if ($recipients === []) {
            return;
        }

        try {
            $this->notificationCreator->createForRecipients($recipients, NotificationType::BandSpaceChatMention, [
                'band_space_id' => (string) $event->bandSpace->id,
                'band_space_name' => $event->bandSpace->name,
                'message_id' => (string) $message->id,
                'actor_id' => $actorId,
                'actor_username' => $message->author->username,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create chat mention notifications', [
                'message_id' => (string) $message->id,
                'exception' => $e,
            ]);
        }
    }
}
