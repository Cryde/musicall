<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Sends the message-received email. The throttle (one email per unread
 * streak, #533), preference and deleted-user checks, and the pending-flag
 * flip all live in MessageSenderProcedure::shouldNotify - by the time we
 * dispatch the event, we have already decided an email should go out.
 *
 * Kept as a listener (rather than calling the email service inline from
 * the procedure) so future side-effects on message-sent (analytics,
 * push notification, etc.) can plug in without touching the procedure.
 */
#[AsEventListener]
readonly class MessageSentListener
{
    public function __construct(
        private MessageReceivedEmail $messageReceivedEmail,
        private RouterInterface      $router,
    ) {
    }

    public function __invoke(MessageSentEvent $event): void
    {
        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $messageUrl = $baseUrl . 'messages/' . $event->thread->id;

        $this->messageReceivedEmail->send(
            $event->recipient->email,
            $event->recipient->username,
            $event->sender->username,
            $messageUrl,
        );
    }
}
