<?php declare(strict_types=1);

namespace App\Tests\Api\Mail;

use App\Messenger\MessageReceivedEmailStamp;
use App\Messenger\SendPushNotification;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Every email is queued on the `email` transport for the worker (#1156), push keeps `async` to itself.
 * The test transport is intercepted here so the queue can be read before anything is sent.
 */
#[ResetDatabase]
class EmailQueueTest extends ApiTestCase
{
    use InteractsWithMessenger;

    public function test_a_direct_message_queues_its_email_with_the_thread_and_recipient_and_pushes_on_async(): void
    {
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@example.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@example.com']);
        $this->transport('email')->intercept();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Salut !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertEmailCount(0);
        $threadId = (string) self::getContainer()->get(MessageThreadMetaRepository::class)->findOneBy(['user' => $recipient->id])?->thread->id;
        $queue = $this->transport('email')->queue();
        $queue->assertCount(1);
        $queue->assertContains(SendEmailMessage::class, 1);
        $queue->first()->assertHasStamp(
            MessageReceivedEmailStamp::class,
            static fn (MessageReceivedEmailStamp $stamp): bool => $stamp->threadId === $threadId && $stamp->recipientId === $recipient->id,
        );
        $this->assertSame([SendPushNotification::class], $this->classesQueuedOnAsync());
    }

    public function test_a_password_reset_email_is_queued_on_the_email_transport_too(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $this->transport('email')->intercept();

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertEmailCount(0);
        $queue = $this->transport('email')->queue();
        $queue->assertCount(1);
        $queue->assertContains(SendEmailMessage::class, 1);
        $queue->first()->assertNotHasStamp(MessageReceivedEmailStamp::class);
        $this->assertSame([], $this->classesQueuedOnAsync());
    }

    /** @return list<class-string> */
    private function classesQueuedOnAsync(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): string => $envelope->getMessage()::class, $transport->getSent()));
    }
}
