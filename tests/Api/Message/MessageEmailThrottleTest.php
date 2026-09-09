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

    public function test_first_message_in_existing_thread_sends_email(): void
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
        $this->assertNull($recipientMeta->lastReadDatetime, 'An incoming message must not move the recipient\'s read position');
    }

    public function test_first_message_in_brand_new_thread_sends_email(): void
    {
        // POST /api/messages/user creates the thread + metas on the fly, so the
        // throttle check runs against an entity that has not been flushed yet.
        // Regression for an earlier blocker: a stale findOneBy in the listener
        // returned null and skipped the email entirely.
        $sender = UserFactory::new()->asBaseUser()->create([
            'username' => 'first_sender',
            'email' => 'first_sender@test.com',
        ]);
        $recipient = UserFactory::new()->asBaseUser()->create([
            'username' => 'first_recipient',
            'email' => 'first_recipient@test.com',
        ]);

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'first hello',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id]);
        $this->assertNotNull($recipientMeta);
        $this->assertTrue($recipientMeta->pendingNotificationSent);
        $this->assertNull($recipientMeta->lastReadDatetime, 'An incoming message must not move the recipient\'s read position');
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
        $recipientMeta->lastReadDatetime = null;
        $em->flush();

        // Recipient reads the thread.
        $this->client->loginUser($recipient);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $recipientMetaId, [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $metaRepo->find($recipientMetaId);
        $this->assertNotNull($reloaded->lastReadDatetime);
        $this->assertFalse(
            $reloaded->pendingNotificationSent,
            'Marking the thread read must reset pending_notification_sent so the next incoming message can email again',
        );
    }

    public function test_marking_an_already_read_thread_does_not_reset_the_streak(): void
    {
        // The other half of the guard, and the half that had no test. "None ever do again" is what
        // happens if the reset fires too readily; this pins that it only fires on catching up. The
        // old boolean expressed it as the unread-to-read transition.
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMetaId = $recipientMeta->id;
        // An email is in flight, and they have already read to the end of the thread.
        $recipientMeta->pendingNotificationSent = true;
        $recipientMeta->lastReadDatetime = new \DateTimeImmutable('+1 hour');
        $em->flush();

        $this->client->loginUser($recipient);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $recipientMetaId, [
            'is_read' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $this->assertTrue(
            $metaRepo->find($recipientMetaId)->pendingNotificationSent,
            'Re-reading a thread with nothing new must leave the streak alone',
        );
    }

    public function test_replying_clears_your_own_streak_so_the_next_message_can_email_you(): void
    {
        // Problem 4 of #957. Before this, being emailed and then replying left the flag set, so the
        // next message to you sent nothing, and with no polling in the message UI you were reachable
        // by neither route until you navigated away and back.
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        // The recipient has been emailed and has not marked the thread read.
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMetaId = $recipientMeta->id;
        $recipientMeta->pendingNotificationSent = true;
        $em->flush();

        // They reply instead of marking it read.
        $this->client->loginUser($recipient);
        $this->postMessage($thread, 'replying rather than opening the inbox');
        $this->assertResponseIsSuccessful();

        $em->clear();
        $this->assertFalse(
            $metaRepo->find($recipientMetaId)->pendingNotificationSent,
            'Replying is the strongest evidence of catching up, so it must clear the streak',
        );
    }

    public function test_no_email_when_recipient_was_recently_active(): void
    {
        // #712: recipient was active <5 min ago -> skip the email entirely
        // (they will see the in-app notification on the inbox tab).
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $recipient->lastActivityDatetime = new \DateTimeImmutable('-30 seconds');
        $em->flush();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'hey are you around?');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);

        // Streak flag must NOT flip - otherwise we would never email this
        // recipient until they read, even after they go idle.
        $em->clear();
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $this->assertFalse($recipientMeta->pendingNotificationSent);
    }

    public function test_email_sent_when_recipient_was_active_long_ago(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $recipient->lastActivityDatetime = new \DateTimeImmutable('-2 hours');
        $em->flush();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'long time no see');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);
    }

    public function test_message_after_read_sends_a_fresh_email(): void
    {
        [$sender, $recipient, $thread] = $this->createThreadWithMembers();

        // State: previous streak already notified, recipient has since read.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepo = self::getContainer()->get(MessageThreadMetaRepository::class);
        $recipientMeta = $metaRepo->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $recipientMeta->pendingNotificationSent = false;
        $recipientMeta->lastReadDatetime = new \DateTimeImmutable();
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
            'lastReadDatetime' => new \DateTimeImmutable(),
        ])->create();
        MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $recipient,
            'lastReadDatetime' => null,
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
