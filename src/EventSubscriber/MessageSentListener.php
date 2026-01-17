<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
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
    ) {
    }

    public function __invoke(MessageSentEvent $event): void
    {
        $recipient = $event->recipient;
        $sender = $event->sender;
        $thread = $event->thread;

        if (!$this->preferenceChecker->canReceiveMessageNotification($recipient)) {
            return;
        }

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $messageUrl = $baseUrl . 'messages/' . $thread->getId();

        $this->messageReceivedEmail->send(
            $recipient->getEmail(),
            $recipient->getUsername(),
            $sender->getUsername(),
            $messageUrl
        );
    }
}
