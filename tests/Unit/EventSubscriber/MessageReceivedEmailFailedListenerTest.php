<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\MessageReceivedEmailFailedListener;
use App\Messenger\MessageReceivedEmailStamp;
use App\Repository\Message\MessageThreadMetaRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mime\Email;

class MessageReceivedEmailFailedListenerTest extends TestCase
{
    public function test_a_release_that_fails_is_logged_and_never_thrown_into_the_worker(): void
    {
        $releaseFailure = new \RuntimeException('Database gone');
        $repository = $this->createMock(MessageThreadMetaRepository::class);
        $repository->expects($this->once())->method('releasePendingNotification')
            ->with('thread-id', 'recipient-id')
            ->willThrowException($releaseFailure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Failed to release the message notification throttle',
            ['thread_id' => 'thread-id', 'recipient_id' => 'recipient-id', 'exception' => $releaseFailure],
        );

        $listener = new MessageReceivedEmailFailedListener($repository, $logger);
        $listener(new WorkerMessageFailedEvent(
            new Envelope(new SendEmailMessage(new Email()), [new MessageReceivedEmailStamp('thread-id', 'recipient-id')]),
            'email',
            new TransportException('Brevo refused the call'),
        ));
    }
}
