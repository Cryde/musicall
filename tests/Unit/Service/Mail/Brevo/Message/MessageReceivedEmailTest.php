<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail\Brevo\Message;

use App\Messenger\MessageReceivedEmailStamp;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

class MessageReceivedEmailTest extends TestCase
{
    public function test_the_queued_email_names_its_thread_and_recipient_in_a_stamp_not_in_a_header(): void
    {
        $bus = new class implements MessageBusInterface {
            public ?Envelope $dispatched = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->dispatched = Envelope::wrap($message, $stamps);
            }
        };

        new MessageReceivedEmail($bus)
            ->send('to@example.com', 'jane', 'john', 'https://www.musicall.com/messages/thread-id', 'fr', 'thread-id', 'recipient-id');

        $stamp = $bus->dispatched?->last(MessageReceivedEmailStamp::class);
        $this->assertEquals(new MessageReceivedEmailStamp('thread-id', 'recipient-id'), $stamp);
        $message = $bus->dispatched?->getMessage();
        $this->assertInstanceOf(SendEmailMessage::class, $message);
        $email = $message->getMessage();
        $this->assertInstanceOf(Email::class, $email);
        // Brevo forwards every header it does not consume, so the recipient id must not ride one.
        $this->assertSame(['from', 'to', 'templateid', 'params'], $email->getHeaders()->getNames());
    }
}
