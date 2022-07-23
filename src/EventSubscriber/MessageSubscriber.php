<?php

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
use App\Service\Mail\MessageReceivedMail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MessageSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MessageReceivedMail $messageReceivedMail)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageSentEvent::NAME => 'onMessageSent',
        ];
    }

    public function onMessageSent(MessageSentEvent $event)
    {
        $recipient = $event->getRecipient();
        // @todo : for now we will only send directly the mail
        // later we will have to check last notifications

        $this->messageReceivedMail->send($recipient->getEmail(), $recipient->getUsername());
    }
}

