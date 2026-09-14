<?php

declare(strict_types=1);

namespace App\Tests\Integration\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
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

    public function test_a_channel_counts_towards_the_navbar_badge_now_the_inbox_lists_it(): void
    {
        // #961 kept channels off the envelope because the inbox below it listed only direct messages,
        // so the number was one a member could neither explain nor clear. #994 lists them and lets
        // them be opened from there, which removes the whole of that reason.
        [$reader, $writer] = $this->twoUsers();

        $directThread = MessageThreadFactory::new()->create();
        $this->messageAt($directThread, $writer, '2026-09-01 10:00:00');
        $this->metaFor($reader, $directThread, null);

        [, $channel] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');
        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-02 10:05:00');
        $this->metaFor($reader, $channel, null);

        self::assertSame(3, $this->messageRepository->countUnreadForUser($reader));

        // And the per-thread map, which the inbox reads row by row, agrees with it. Asserted key by
        // key because the order of a GROUP BY result is not something to depend on.
        $byThread = $this->messageRepository->countUnreadByThreadForUser($reader);
        self::assertCount(2, $byThread);
        self::assertSame(1, $byThread[(string) $directThread->id]);
        self::assertSame(2, $byThread[(string) $channel->id]);
    }

    public function test_a_channel_stops_counting_for_somebody_who_has_left_the_band(): void
    {
        // The read-state row survives a leave or a kick (#948 concerns 1 and 3), so without the
        // membership join the count would keep running for somebody who is no longer in the band, in
        // the envelope and on every inbox row alike.
        [$reader, $writer] = $this->twoUsers();
        [, $channel, $membership] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');
        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->metaFor($reader, $channel, null);

        self::assertSame(1, $this->messageRepository->countUnreadForUser($reader));

        $membership->status = MembershipStatus::Kicked;
        \Zenstruck\Foundry\Persistence\save($membership);

        self::assertSame(0, $this->messageRepository->countUnreadForUser($reader));
        self::assertSame([], $this->messageRepository->countUnreadByThreadForUser($reader));
    }

    public function test_a_new_member_is_not_told_the_whole_history_is_unread(): void
    {
        // A member's read-state row is created lazily by the first message after they join, with no
        // read position, so without a floor at the moment they joined everything said before they
        // arrived reads as unread. The sidebar badge has always known this; the inbox count and the
        // envelope learned it in #994.
        [$reader, $writer] = $this->twoUsers();
        [, $channel] = $this->bandWith($reader, $writer, '2026-09-10 12:00:00');
        $this->messageAt($channel, $writer, '2026-09-01 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-05 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-11 10:00:00');
        $this->metaFor($reader, $channel, null);

        self::assertSame(1, $this->messageRepository->countUnreadForUser($reader));
        self::assertSame(
            1,
            $this->messageRepository->countUnreadByThreadForUser($reader)[(string) $channel->id],
            'The inbox row must say what the sidebar badge says, or the two disagree for one band',
        );
        self::assertSame(
            1,
            $this->messageRepository->countUnreadChannelsForUser($reader)[(string) $channel->bandSpace->id],
        );
    }

    public function test_the_single_thread_count_agrees_with_the_list_for_a_new_member(): void
    {
        // Three methods answer "how much is unread here" and a row is served by two of them: the list
        // builds the inbox, this one answers the PATCH that opens a conversation. A floor in one and
        // not the other would make the same row report two numbers.
        [$reader, $writer] = $this->twoUsers();
        [, $channel] = $this->bandWith($reader, $writer, '2026-09-10 12:00:00');
        $this->messageAt($channel, $writer, '2026-09-01 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-11 10:00:00');
        $meta = $this->metaFor($reader, $channel, null);

        self::assertSame(1, $this->messageRepository->countUnreadForThread($meta));
        self::assertSame(
            $this->messageRepository->countUnreadByThreadForUser($reader)[(string) $channel->id],
            $this->messageRepository->countUnreadForThread($meta),
        );
    }

    public function test_the_single_thread_count_tells_a_former_member_nothing(): void
    {
        // Their read-state row survives the kick, and its id is all this endpoint takes. Without the
        // membership join they could still learn how much the band has said since, which is a smaller
        // leak than the messages themselves and the same one.
        [$reader, $writer] = $this->twoUsers();
        [, $channel, $membership] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');
        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-03 10:00:00');
        $meta = $this->metaFor($reader, $channel, null);

        $membership->status = MembershipStatus::Kicked;
        \Zenstruck\Foundry\Persistence\save($membership);

        self::assertSame(0, $this->messageRepository->countUnreadForThread($meta));
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

    public function test_a_channel_counts_what_arrived_after_the_read_position(): void
    {
        [$reader, $writer] = $this->twoUsers();
        [$space, $channel] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');

        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-02 10:05:00');
        $this->messageAt($channel, $reader, '2026-09-02 10:10:00');
        $this->metaFor($reader, $channel, new \DateTimeImmutable('2026-09-02 10:00:00'));

        // The reader's own message never counts, and the one at the read position is already read.
        self::assertSame(
            [(string) $space->id => 1],
            $this->messageRepository->countUnreadChannelsForUser($reader)
        );
    }

    public function test_a_channel_that_is_fully_read_is_absent(): void
    {
        [$reader, $writer] = $this->twoUsers();
        [, $channel] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');

        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->metaFor($reader, $channel, new \DateTimeImmutable('2026-09-02 10:00:01'));

        // Absent rather than present with a zero, like the per-thread map, so callers read it with ?? 0.
        self::assertSame([], $this->messageRepository->countUnreadChannelsForUser($reader));
    }

    public function test_a_member_does_not_inherit_the_history_from_before_they_joined(): void
    {
        // Their read-state row is created lazily by the first message after they join, with no read
        // position, so without the membership floor the whole back catalogue would read as unread.
        [$reader, $writer] = $this->twoUsers();
        [$space, $channel] = $this->bandWith($reader, $writer, '2026-09-10 09:00:00');

        $this->messageAt($channel, $writer, '2026-09-01 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-05 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-11 10:00:00');
        $this->metaFor($reader, $channel, null);

        self::assertSame(
            [(string) $space->id => 1],
            $this->messageRepository->countUnreadChannelsForUser($reader)
        );
    }

    public function test_a_former_member_is_not_counted_even_though_their_row_survives(): void
    {
        // Leaving does not delete the read-state row (#948 concern 3). The membership join is what
        // keeps it out of the count, so nothing has to be cleaned up when somebody goes.
        [$reader, $writer] = $this->twoUsers();
        [, $channel, $membership] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');

        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->metaFor($reader, $channel, null);

        $membership->status = MembershipStatus::Kicked;
        \Zenstruck\Foundry\Persistence\save($membership);

        self::assertSame([], $this->messageRepository->countUnreadChannelsForUser($reader));
    }

    public function test_rejoining_starts_the_read_position_again(): void
    {
        // What the invitation paths call when they reactivate a membership: without it a rejoining
        // member is handed everything said while they were gone.
        [$reader, $writer] = $this->twoUsers();
        [$space, $channel] = $this->bandWith($reader, $writer, '2026-09-01 09:00:00');

        $this->messageAt($channel, $writer, '2026-09-02 10:00:00');
        $this->messageAt($channel, $writer, '2026-09-03 10:00:00');
        $this->metaFor($reader, $channel, new \DateTimeImmutable('2026-09-01 10:00:00'));

        self::assertSame(
            [(string) $space->id => 2],
            $this->messageRepository->countUnreadChannelsForUser($reader)
        );

        self::getContainer()->get(MessageThreadMetaRepository::class)
            ->markChannelsReadForUser($space, $reader);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame([], $this->messageRepository->countUnreadChannelsForUser($reader));
    }

    public function test_rejoining_after_a_first_stint_that_saw_nothing_starts_from_the_rejoin(): void
    {
        // The case an update-only reset silently missed. Somebody invited, then removed before anyone
        // wrote, never got a read-state row at all, so there was nothing to update. Their row was then
        // created by the first message after they came back, with no read position, and the floor is
        // their *original* join, so everything said while they were gone counted. Measured at 3.
        [$reader, $writer] = $this->twoUsers();
        [$space, $channel, $membership] = $this->bandWith($reader, $writer, '2026-01-01 09:00:00');

        $membership->status = MembershipStatus::Kicked;
        \Zenstruck\Foundry\Persistence\save($membership);

        $this->messageAt($channel, $writer, '2026-05-01 10:00:00');
        $this->messageAt($channel, $writer, '2026-06-01 10:00:00');

        $membership->status = MembershipStatus::Active;
        \Zenstruck\Foundry\Persistence\save($membership);
        self::getContainer()->get(MessageThreadMetaRepository::class)
            ->markChannelsReadForUser($space, $reader);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Nothing from the months they were gone.
        self::assertSame([], $this->messageRepository->countUnreadChannelsForUser($reader));

        // And the conversation carries on from there.
        $this->messageAt($channel, $writer, '+1 hour');

        self::assertSame(
            [(string) $space->id => 1],
            $this->messageRepository->countUnreadChannelsForUser($reader)
        );
    }

    /**
     * A band both users are active members of, with its channel. The membership date is pinned
     * because it is the floor the count uses, and the factory would otherwise pick a random one.
     *
     * @return array{0: BandSpace, 1: MessageThread, 2: BandSpaceMembership}
     */
    private function bandWith(User $reader, User $writer, string $joinedAt): array
    {
        $space = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $reader,
            'creationDatetime' => new \DateTime($joinedAt),
        ])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $writer,
            'creationDatetime' => new \DateTime($joinedAt),
        ])->create();

        return [$space, MessageThreadFactory::new()->forBandSpace($space)->create(), $membership];
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
