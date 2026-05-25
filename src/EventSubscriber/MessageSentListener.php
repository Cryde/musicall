<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Message\MessageThreadMeta;
use App\Event\MessageSentEvent;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use App\Service\User\UserNotificationPreferenceChecker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener]
readonly class MessageSentListener
{
    public function __construct(
        private MessageReceivedEmail              $messageReceivedEmail,
        private RouterInterface                   $router,
        private UserNotificationPreferenceChecker $preferenceChecker,
        private MessageThreadMetaRepository       $messageThreadMetaRepository,
    ) {
    }

    public function __invoke(MessageSentEvent $event): void
    {
        $recipient = $event->recipient;
        $sender = $event->sender;
        $thread = $event->thread;

        if ($recipient->isDeleted() || !$this->preferenceChecker->canReceiveMessageNotification($recipient)) {
            return;
        }

        // One email per unread streak (#533): if a notification was already
        // sent for the recipient's current unread streak in this thread, skip.
        // The streak resets when the recipient marks the thread as read
        // (MessageThreadMetaPatchProcessor flips pendingNotificationSent back
        // to false).
        $meta = $this->messageThreadMetaRepository->findOneBy(['thread' => $thread, 'user' => $recipient]);
        if (!$meta instanceof MessageThreadMeta || $meta->pendingNotificationSent) {
            return;
        }

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $messageUrl = $baseUrl . 'messages/' . $thread->id;

        $this->messageReceivedEmail->send(
            $recipient->email,
            $recipient->username,
            $sender->username,
            $messageUrl
        );

        $meta->pendingNotificationSent = true;
        // The procedure's outer transaction will flush this together with the
        // message itself; no explicit flush here.
    }
}
