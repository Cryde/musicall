<?php

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class MessageSentListener
{
    public function __construct(private MessageReceivedEmail $messageReceivedEmail)
    {
    }

    public function __invoke(MessageSentEvent $event): void
    {
        $recipient = $event->recipient;
        // @todo : for now we will only send directly the mail
        // later we will have to check last notifications

        $this->messageReceivedEmail->send($recipient->getEmail(), $recipient->getUsername());
    }
}
