<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThreadMeta;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportException;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A mailer that refuses the "new message" email must not fail the message itself (#1151).
 */
#[ResetDatabase]
class MessageEmailFailureTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_failing_email_still_sends_the_message_and_releases_the_throttle(): void
    {
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@example.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@example.com']);
        $failingEmail = $this->createMock(MessageReceivedEmail::class);
        $failingEmail->expects($this->once())->method('send')->willThrowException(new TransportException('Brevo refused the call'));
        self::getContainer()->set(MessageReceivedEmail::class, $failingEmail);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Salut !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $messages = $entityManager->getRepository(Message::class)->findAll();
        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $message->id,
            '@type' => 'Message',
            'id' => $message->id,
            'creation_datetime' => $message->creationDatetime->format('c'),
            'author' => [
                '@id' => '/api/users/' . $sender->id,
                '@type' => 'User',
                'id' => $sender->id,
                'username' => 'sender',
                'display_name' => 'sender',
            ],
            'thread' => [
                '@id' => '/api/message_threads/' . $message->thread->id,
                '@type' => 'MessageThread',
                'id' => $message->thread->id,
            ],
            'content' => 'Salut !',
            'content_preview' => 'Salut !',
        ]);

        // The throttle flag was set before the email was attempted. Left set, this thread would
        // never email the recipient again until they read it.
        $recipientMeta = $entityManager->getRepository(MessageThreadMeta::class)->findOneBy([
            'thread' => (string) $message->thread->id,
            'user' => (string) $recipient->id,
        ]);
        $this->assertFalse($recipientMeta?->pendingNotificationSent);
    }
}
