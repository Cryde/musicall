<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Event\MessageSentEvent;
use App\EventSubscriber\MessageSentListener;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

class MessageSentListenerTest extends TestCase
{
    public function test_the_email_follows_the_recipient_locale_not_the_sender_one(): void
    {
        $recipient = new User();
        $recipient->email = 'recipient@example.com';
        $recipient->username = 'recipient';
        $recipient->locale = 'en';
        $sender = new User();
        $sender->username = 'sender';
        $sender->locale = 'fr';
        $thread = new MessageThread();
        $thread->id = 'thread-id';

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('https://musicall.test/');
        $messageReceivedEmail = $this->createMock(MessageReceivedEmail::class);
        $messageReceivedEmail->expects($this->once())->method('send')
            ->with('recipient@example.com', 'recipient', 'sender', 'https://musicall.test/messages/thread-id', 'en');
        // The listener logs and swallows any send failure, a mismatched argument included: this makes it loud.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $listener = new MessageSentListener(
            $messageReceivedEmail,
            $router,
            $this->createStub(MessageThreadMetaRepository::class),
            $logger,
        );
        $listener(new MessageSentEvent($recipient, $sender, $thread));
    }
}
