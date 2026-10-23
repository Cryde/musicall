<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The live half of « Vu » in a direct conversation (#1243): reading tells the other person, and only
 * when the read reached a message of theirs it had not reached before.
 */
#[ResetDatabase]
class MessageReadSignalTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_read_that_reaches_their_message_signals_them(): void
    {
        [$reader, $writer, $thread] = $this->conversation();
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: '2026-10-20 09:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $writer->id)], $hub->updates[0]->getTopics());
        $this->assertTrue($hub->updates[0]->isPrivate());
        // A tag: no reader, no position. Their tab re-reads the page through the API.
        $this->assertSame('{"type":"message_read","thread_id":"' . $thread->id . '"}', $hub->updates[0]->getData());
    }

    public function test_reading_again_after_marking_unread_publishes_nothing(): void
    {
        // « Marquer comme non lu » emptied the read position, not the seen one: nothing new is seen.
        [$reader, $writer, $thread] = $this->conversation();
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: '2026-10-20 11:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_only_your_own_message_past_your_position_publishes_nothing(): void
    {
        [$reader, , $thread] = $this->conversation();
        $this->message($thread, $reader, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: null);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_marking_unread_publishes_nothing_and_keeps_the_seen_position(): void
    {
        [$reader, $writer, $thread] = $this->conversation();
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: '2026-10-20 09:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, false);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 1,
        ]);
        $stored = $this->reload($meta);
        $this->assertNull($stored->lastReadDatetime);
        $this->assertEquals(new \DateTimeImmutable('2026-10-20 09:00:00'), $stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    /** @return iterable<string, array{string}> */
    public static function receiptsThatDoNotApply(): iterable
    {
        yield 'the writer hides that they read' => ['writer_opted_out'];
        yield 'blocked either way' => ['blocked'];
        yield 'the writer account is gone' => ['writer_deleted'];
    }

    #[DataProvider('receiptsThatDoNotApply')]
    public function test_nobody_hears_a_read_the_receipt_does_not_apply_to(string $case): void
    {
        $reader = $this->user('alice_drums');
        $writer = $case === 'writer_deleted'
            ? UserFactory::new()->asBaseUser()->create([
                'username' => 'bob_bass',
                'email' => 'bob_bass@test.com',
                'deletionDatetime' => new \DateTimeImmutable('2026-10-21 10:00:00'),
            ])
            : $this->user('bob_bass');
        match ($case) {
            'writer_opted_out' => $this->hideReceipts($writer),
            'blocked' => UserBlockFactory::new()->create(['blocker' => $writer, 'blocked' => $reader]),
            default => null,
        };
        $thread = $this->directThread($reader, $writer);
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: null);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_reading_while_hiding_receipts_records_nothing_seen(): void
    {
        // So turning receipts back on later reveals nothing read in the meantime.
        [$reader, $writer, $thread] = $this->conversation();
        $this->hideReceipts($reader);
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: null);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertNull($stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_band_channel_marked_read_from_the_inbox_sends_no_direct_signal(): void
    {
        // A channel has its own « Vu par » signal, sent by its own read endpoint.
        $reader = $this->user('alice_drums');
        $writer = $this->user('bob_bass');
        $channel = MessageThreadFactory::new()->forBandSpace(BandSpaceFactory::new()->create())->create();
        $this->message($channel, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($channel, $reader, seen: null);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_hub_that_is_down_leaves_the_read_answering_as_it_always_has(): void
    {
        [$reader, $writer, $thread] = $this->conversation();
        $this->message($thread, $writer, '2026-10-20 10:00:00');
        $meta = $this->positions($thread, $reader, seen: null);
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->markRead($reader, $meta, true);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas/' . $meta->id,
            '@type' => 'MessageThreadMeta',
            'id' => $meta->id,
            'unread_count' => 0,
        ]);
        $stored = $this->reload($meta);
        $this->assertNotNull($stored->lastReadDatetime);
        $this->assertEquals($stored->lastReadDatetime, $stored->lastSeenDatetime);
    }

    private function markRead(User $reader, MessageThreadMeta $meta, bool $isRead): void
    {
        $this->client->loginUser($reader);
        $this->client->jsonRequest('PATCH', '/api/message_thread_metas/' . $meta->id, [
            'is_read' => $isRead,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
    }

    private function reload(MessageThreadMeta $meta): MessageThreadMeta
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(MessageThreadMeta::class, $meta->id);
    }

    /** @return array{0: User, 1: User, 2: MessageThread} */
    private function conversation(): array
    {
        $reader = $this->user('alice_drums');
        $writer = $this->user('bob_bass');

        return [$reader, $writer, $this->directThread($reader, $writer)];
    }

    private function user(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    private function directThread(User $first, User $second): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $first])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $second])->create();

        return $thread;
    }

    private function message(MessageThread $thread, User $author, string $at): void
    {
        $message = MessageFactory::new([
            'author' => $author,
            'thread' => $thread,
            'content' => 'salut',
            'creationDatetime' => new \DateTime($at),
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
    }

    private function positions(MessageThread $thread, User $user, ?string $seen): MessageThreadMeta
    {
        return MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $user,
            'lastReadDatetime' => null,
            'lastSeenDatetime' => $seen === null ? null : new \DateTimeImmutable($seen),
        ])->create();
    }

    private function hideReceipts(User $user): void
    {
        $preference = new UserNotificationPreference();
        $preference->user = $user;
        $preference->showReadReceipts = false;
        $user->notificationPreference = $preference;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($preference);
        $entityManager->flush();
    }
}
