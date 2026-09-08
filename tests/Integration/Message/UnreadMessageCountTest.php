<?php

declare(strict_types=1);

namespace App\Tests\Integration\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * What the read position bought over the boolean it replaced (#954): a number rather than a dot.
 *
 * These sit at the query rather than at the API, because that is where the semantics live and the
 * cases are all about *what counts*. The contract that carries the number to the browser is asserted
 * in full in tests/Api/Message/MessageThreadMetaGetCollectionTest.
 */
#[ResetDatabase]
class UnreadMessageCountTest extends KernelTestCase
{
    private MessageRepository $messageRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->messageRepository = self::getContainer()->get(MessageRepository::class);
    }

    public function test_a_position_mid_thread_counts_only_what_came_after_it(): void
    {
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        $this->messageAt($thread, $writer, '2026-09-01 10:00:00');
        $this->messageAt($thread, $writer, '2026-09-01 10:05:00');
        $this->messageAt($thread, $writer, '2026-09-01 10:10:00');

        $meta = $this->metaFor($reader, $thread, new \DateTimeImmutable('2026-09-01 10:02:00'));

        self::assertSame(2, $this->messageRepository->countUnreadForThread($meta));
        self::assertSame(2, $this->messageRepository->countUnreadForUser($reader));
        self::assertSame(
            [(string) $thread->id => 2],
            $this->messageRepository->countUnreadByThreadForUser($reader)
        );
    }

    public function test_no_position_counts_the_whole_thread(): void
    {
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        $this->messageAt($thread, $writer, '2026-09-01 10:00:00');
        $this->messageAt($thread, $writer, '2026-09-01 10:05:00');

        self::assertSame(2, $this->messageRepository->countUnreadForThread($this->metaFor($reader, $thread, null)));
    }

    public function test_your_own_messages_never_count(): void
    {
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        // Everything after the position, but two of the three written by the reader.
        $this->messageAt($thread, $reader, '2026-09-01 10:00:00');
        $this->messageAt($thread, $reader, '2026-09-01 10:05:00');
        $this->messageAt($thread, $writer, '2026-09-01 10:10:00');

        self::assertSame(1, $this->messageRepository->countUnreadForThread($this->metaFor($reader, $thread, null)));
    }

    public function test_a_position_past_the_last_message_counts_nothing(): void
    {
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        $this->messageAt($thread, $writer, '2026-09-01 10:00:00');
        $meta = $this->metaFor($reader, $thread, new \DateTimeImmutable('2026-09-01 10:00:01'));

        self::assertSame(0, $this->messageRepository->countUnreadForThread($meta));
        // Absent from the grouped result rather than present as a zero, which is why its callers
        // read it with `?? 0`.
        self::assertSame([], $this->messageRepository->countUnreadByThreadForUser($reader));
    }

    public function test_a_message_in_the_same_second_as_the_position_reads_as_already_read(): void
    {
        // The documented cost of a one-second column. Pinned so it is a known limit rather than a
        // surprise, and so anyone moving message.creation_datetime to microseconds sees this fail.
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        $this->messageAt($thread, $writer, '2026-09-01 10:00:00');
        $meta = $this->metaFor($reader, $thread, new \DateTimeImmutable('2026-09-01 10:00:00'));

        self::assertSame(0, $this->messageRepository->countUnreadForThread($meta));
    }

    public function test_the_total_ignores_a_thread_the_user_deleted(): void
    {
        // The navbar count used to ignore isDeleted while the inbox beside it did not, so the two
        // disagreed. Nothing sets the flag today, which is the only reason it never showed.
        [$reader, $writer] = $this->twoUsers();

        $kept = MessageThreadFactory::new()->create();
        $this->messageAt($kept, $writer, '2026-09-01 10:00:00');
        $this->metaFor($reader, $kept, null);

        $deleted = MessageThreadFactory::new()->create();
        $this->messageAt($deleted, $writer, '2026-09-02 10:00:00');
        $this->messageAt($deleted, $writer, '2026-09-02 10:05:00');
        MessageThreadMetaFactory::new([
            'user' => $reader,
            'thread' => $deleted,
            'lastReadDatetime' => null,
            'isDeleted' => true,
        ])->create();

        self::assertSame(1, $this->messageRepository->countUnreadForUser($reader));
    }

    public function test_another_persons_unread_never_leaks_into_yours(): void
    {
        [$reader, $writer] = $this->twoUsers();
        $thread = MessageThreadFactory::new()->create();

        $this->messageAt($thread, $reader, '2026-09-01 10:00:00');
        $this->metaFor($writer, $thread, null);

        self::assertSame(0, $this->messageRepository->countUnreadForUser($reader));
        self::assertSame(1, $this->messageRepository->countUnreadForUser($writer));
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoUsers(): array
    {
        return [
            UserFactory::new()->asBaseUser()->create(['username' => 'reader', 'email' => 'reader@test.com']),
            UserFactory::new()->asBaseUser()->create(['username' => 'writer', 'email' => 'writer@test.com']),
        ];
    }

    private function metaFor(User $user, MessageThread $thread, ?\DateTimeImmutable $lastRead): MessageThreadMeta
    {
        return MessageThreadMetaFactory::new([
            'user' => $user,
            'thread' => $thread,
            'lastReadDatetime' => $lastRead,
        ])->create();
    }

    private function messageAt(MessageThread $thread, User $author, string $at): void
    {
        MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'creationDatetime' => new \DateTime($at),
        ])->create();
    }
}
