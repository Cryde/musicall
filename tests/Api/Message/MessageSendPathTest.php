<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The hardening from #957, which is all about behaviour that is invisible in a two-person
 * conversation and becomes the normal case once a thread has three participants.
 *
 * The concurrency itself is not testable here: DAMA wraps every test in a transaction, so a second
 * connection taking the same lock would block until the test timed out rather than assert anything.
 * What is testable, and what these pin, is that the lock statement is issued at all and that the
 * per-participant lookup is one query.
 */
#[ResetDatabase]
class MessageSendPathTest extends ApiTestCase
{
    public function test_sending_locks_the_thread_row(): void
    {
        [$sender, $recipient] = $this->twoUsers();
        $thread = $this->threadWith($sender, $recipient);

        $this->client->loginUser($sender);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->postMessage($thread, 'hello');

        $this->assertResponseIsSuccessful();

        // Matched in full rather than by searching for `message_thread`, which is a prefix of
        // `message_thread_meta` and so would have passed on the wrong table.
        $this->assertNotSame(
            [],
            $this->queriesMatching('FROM message_thread t0 WHERE t0.id = ? FOR UPDATE'),
            'The send path must take a write lock on the thread row',
        );
        // And the users, before the thread. The order is the whole point: the flush at the end takes
        // locks on fos_user rows, so a transaction that took the thread first would deadlock against
        // process(), which takes the users first. See MessageSenderProcedure::lockThread().
        $locks = $this->queriesMatching('FOR UPDATE');
        $userLock = $this->firstIndexMatching($locks, 'FROM fos_user');
        $threadLock = $this->firstIndexMatching($locks, 'FROM message_thread t0');
        $this->assertNotNull($userLock, 'The send path must take a write lock on the participants');
        $this->assertLessThan(
            $threadLock,
            $userLock,
            'The participants must be locked before the thread, or the two entry points deadlock',
        );
    }

    public function test_the_read_state_lookup_is_one_query_whatever_the_participant_count(): void
    {
        // Four participants, so a per-participant findOneBy would show up as four selects.
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $sender, 'lastReadDatetime' => null])->create();

        foreach (range(1, 3) as $index) {
            $other = UserFactory::new()->asBaseUser()->create([
                'username' => 'other_' . $index,
                'email' => 'other' . $index . '@test.com',
            ]);
            MessageParticipantFactory::new(['thread' => $thread, 'participant' => $other])->create();
            MessageThreadMetaFactory::new(['thread' => $thread, 'user' => $other, 'lastReadDatetime' => null])->create();
        }

        $this->client->loginUser($sender);
        $this->client->enableProfiler();
        // Deliberately no EntityManager::clear() here, unlike the read-side query-count tests: the
        // request persists a Message whose author is the logged-in user, and detaching that user
        // makes Doctrine treat it as a new entity. The count is accurate anyway, because the lookup
        // under test is DQL and DQL always goes to the database.
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->postMessage($thread, 'hello everyone');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            1,
            $this->queriesMatching('FROM message_thread_meta'),
            'The read-state rows must be fetched in one query, not one per participant',
        );
    }

    public function test_a_participant_with_no_read_state_row_still_gets_notified(): void
    {
        // The case that is silent in production: the old code asserted the row existed, and
        // assertions are disabled there, so the notification was skipped with nothing logged. It
        // becomes the normal case for a channel, where a member who never opened it has no row.
        [$sender, $recipient] = $this->twoUsers();
        $thread = $this->threadWith($sender, $recipient);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metaRepository = self::getContainer()->get(MessageThreadMetaRepository::class);
        $entityManager->remove($metaRepository->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]));
        $entityManager->flush();

        $this->client->loginUser($sender);
        $this->postMessage($thread, 'you have no meta row');

        $this->assertResponseIsSuccessful();

        $entityManager->clear();
        $created = $metaRepository->findOneBy(['user' => $recipient->id, 'thread' => $thread->id]);
        $this->assertNotNull($created, 'The missing row must be created rather than skipped');
        $this->assertNull(
            $created->lastReadDatetime,
            'Somebody who has never opened the thread has read none of it',
        );
        $this->assertTrue($created->pendingNotificationSent);
        $this->assertEmailCount(1, message: 'A missing read-state row must not swallow the notification');
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoUsers(): array
    {
        return [
            UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']),
            UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@test.com']),
        ];
    }

    private function threadWith(User $sender, User $recipient): MessageThread
    {
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

        return $thread;
    }

    private function postMessage(MessageThread $thread, string $content): void
    {
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread' => '/api/message_threads/' . $thread->id,
            'content' => $content,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
    }

    /**
     * @param list<string> $queries
     */
    private function firstIndexMatching(array $queries, string $needle): ?int
    {
        foreach ($queries as $index => $sql) {
            if (str_contains($sql, $needle)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function queriesMatching(string $needle): array
    {
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');

        $matching = [];
        foreach ($profile->getCollector('db')->getQueries()['default'] ?? [] as $query) {
            $sql = (string) $query['sql'];
            if (str_contains($sql, $needle)) {
                $matching[] = $sql;
            }
        }

        return $matching;
    }
}
