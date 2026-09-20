<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Mercure\MercureTopic;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The live half of « Vu par » (#977): marking the chat read tells the other members, and only when it
 * changed something, since a read that always published would let every viewer's refetch-then-read
 * multiply across the band.
 */
#[ResetDatabase]
class ChatReadSignalTest extends ApiTestCase
{
    public function test_a_read_that_covers_a_new_message_signals_the_other_active_members_once(): void
    {
        [$space, $channel, $reader, $writer, $third, $left, $kicked, $elsewhere] = $this->band();
        $this->message($channel, $writer, '2026-09-02 10:00:00');
        MessageThreadMetaFactory::new([
            'thread' => $channel,
            'user' => $reader,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-02 09:00:00'),
        ])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());

        // The absences by name first, so a leak reads as "the kicked member" rather than as a uuid.
        foreach ([$reader, $left, $kicked, $elsewhere] as $excluded) {
            $this->assertNotContains(MercureTopic::userNotifications((string) $excluded->id), $hub->publishedTopics());
        }
        $this->assertCount(1, $hub->updates);
        $this->assertSame(
            [
                MercureTopic::userNotifications((string) $writer->id),
                MercureTopic::userNotifications((string) $third->id),
            ],
            $hub->updates[0]->getTopics(),
        );
        $this->assertTrue($hub->updates[0]->isPrivate());
        // A tag: no reader, no position, no content. The browser re-reads the page through the API.
        $this->assertSame(
            '{"type":"band_space_chat_read","band_space_id":"' . $space->id . '"}',
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_member_who_never_opened_the_chat_signals_on_their_first_read(): void
    {
        // Membership is derived, so a member who has never opened the tab has no read-state row at
        // all, and absent has to count as behind rather than as nothing to report.
        [$space, $channel, $reader, $writer] = $this->band();
        $this->message($channel, $writer, '2026-09-02 10:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
    }

    public function test_reading_again_with_nothing_new_publishes_nothing(): void
    {
        // The first rule against the cascade: every viewer marks read after every refetch, so a read
        // that published regardless would signal the whole band for a click that changed nothing.
        [$space, $channel, $reader, $writer] = $this->band();
        $this->message($channel, $writer, '2026-09-02 10:00:00');
        MessageThreadMetaFactory::new([
            'thread' => $channel,
            'user' => $reader,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-02 10:05:00'),
        ])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([], $hub->updates);
    }

    public function test_a_read_position_in_the_same_second_as_the_message_has_already_read_it(): void
    {
        // `>=` is what « Vu par » counts, so the position has to be strictly behind to be moved.
        [$space, $channel, $reader, $writer] = $this->band();
        $this->message($channel, $writer, '2026-09-02 10:00:00');
        MessageThreadMetaFactory::new([
            'thread' => $channel,
            'user' => $reader,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-02 10:00:00'),
        ])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $hub->updates);
    }

    public function test_only_your_own_message_past_your_position_publishes_nothing(): void
    {
        // The author never counts as a reader, so reading past your own message changes no « Vu par ».
        [$space, $channel, $reader] = $this->band();
        $this->message($channel, $reader, '2026-09-02 10:00:00');
        $this->readerPositionAt($channel, $reader, '2026-09-02 09:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $hub->updates);
    }

    public function test_only_a_tombstone_past_your_position_publishes_nothing(): void
    {
        // A deleted message reports no reader, so reading past it changes no « Vu par » either.
        [$space, $channel, $reader, $writer] = $this->band();
        MessageFactory::new([
            'thread' => $channel,
            'author' => $writer,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-02 10:01:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-02 10:02:00'),
        ])->create();
        $this->readerPositionAt($channel, $reader, '2026-09-02 09:00:00');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_hub_that_is_down_leaves_the_read_answering_as_it_always_has(): void
    {
        // The read is the member's own action and the signal a side channel: a dead hub must cost
        // the others a live update, never the reader their badge.
        [$space, $channel, $reader, $writer] = $this->band();
        $this->message($channel, $writer, '2026-09-02 10:00:00');
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($reader);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame(
            [],
            self::getContainer()->get(MessageRepository::class)->countUnreadChannelsForUser($reader),
        );
    }

    /**
     * A reader, two other active members, one who left, one kicked, and somebody active in another
     * space, which is the whole set of people who must or must not hear a read.
     *
     * @return array{0: BandSpace, 1: MessageThread, 2: User, 3: User, 4: User, 5: User, 6: User, 7: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create();
        // Pinned join dates: the recipients come back in that order, and the exact topic list above
        // would otherwise flip on a faker tie.
        $reader = $this->member($space, 'batteur', '2026-09-01 09:00:00');
        $writer = $this->member($space, 'bassiste', '2026-09-01 09:01:00');
        $third = $this->member($space, 'chanteuse', '2026-09-01 09:02:00');
        $left = $this->member($space, 'ancien', '2026-09-01 09:03:00', MembershipStatus::Left);
        $kicked = $this->member($space, 'exclu', '2026-09-01 09:04:00', MembershipStatus::Kicked);
        $elsewhere = $this->member(BandSpaceFactory::new()->create(), 'voisin', '2026-09-01 09:05:00');

        return [
            $space,
            MessageThreadFactory::new()->forBandSpace($space)->create(),
            $reader,
            $writer,
            $third,
            $left,
            $kicked,
            $elsewhere,
        ];
    }

    private function member(
        BandSpace $bandSpace,
        string $username,
        string $joinedAt,
        MembershipStatus $status = MembershipStatus::Active,
    ): User {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => $status,
            'creationDatetime' => new \DateTime($joinedAt),
        ])->create();

        return $user;
    }

    private function readerPositionAt(MessageThread $channel, User $reader, string $at): void
    {
        MessageThreadMetaFactory::new([
            'thread' => $channel,
            'user' => $reader,
            'lastReadDatetime' => new \DateTimeImmutable($at),
        ])->create();
    }

    private function message(MessageThread $channel, User $author, string $at): void
    {
        MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'creationDatetime' => new \DateTime($at),
        ])->create();
    }
}
