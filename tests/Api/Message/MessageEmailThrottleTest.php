<?php declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MessageEmailThrottleTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_first_message_sends_email(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'hello');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $this->assertTrue($recipientMeta->pendingNotificationSent);
        $this->assertFalse($recipientMeta->isRead);
    }

    public function test_second_message_in_same_unread_streak_does_not_send_email(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        // Pre-set the flag as if the first message's email already went out.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMeta->pendingNotificationSent = true;
        $em->flush();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'second message');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
    }

    public function test_marking_thread_as_read_resets_the_streak(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        // Simulate the prior state: an email was already sent for this streak.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMetaId = $recipientMeta->id;
        $recipientMeta->pendingNotificationSent = true;
        $recipientMeta->isRead = false;
        $em->flush();

        // Recipient reads the thread.
        $this->client->loginUser($recipient);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $recipientMetaId, [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $metaRepo->find($recipientMetaId);
        $this->assertTrue($reloaded->isRead);
        $this->assertFalse(
            $reloaded->pendingNotificationSent,
            'Flipping is_read true must reset pending_notification_sent so the next incoming message can email again',
        );
    }

    public function test_message_after_read_sends_a_fresh_email(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        // State: previous streak already notified, recipient has since read.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMeta->pendingNotificationSent = false;
        $recipientMeta->isRead = true;
        $em->flush();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'are you still there?');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);
    }

    /**
     * @return array{0: object, 1: object, 2: object}
     */
    private function createThreadWithMembers(): array
    {
        $sender = UserFactory::new()->asBaseUser()->create([
            'username' => 'sender_user',
            'email' => 'sender@test.com',
        ]);
        $recipient = UserFactory::new()->asBaseUser()->create([
            'username' => 'recipient_user',
            'email' => 'recipient@test.com',
        ]);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $recipient])->create();
        MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $sender,
            'isRead' => true,
        ])->create();
        MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $recipient,
            'isRead' => false,
        ])->create();

        return [$sender, $recipient, $thread];
    }

    private function postMessage($thread, string $content): void
    {
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread' => '/api/message_threads/' . $thread->id,
            'content' => $content,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
    }
}
