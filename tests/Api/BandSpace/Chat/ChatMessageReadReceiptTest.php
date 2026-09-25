<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « Vu par » on a chat message (#977).
 *
 * Read state is a position per member (#954) rather than a flag per message, so all of this is one
 * comparison: has this member's `lastReadDatetime` reached the message. What the tests below pin is
 * who is allowed into that comparison at all.
 */
#[ResetDatabase]
class ChatMessageReadReceiptTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string SENT_AT = '2026-09-10 20:00:00';
    private const string SENT_AT_ISO = '2026-09-10T20:00:00+00:00';

    public function test_a_message_nobody_has_read_names_nobody(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $viewer = $this->member($space, 'batteur');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        // Present but empty, which is the state a member is in before they open the tab.
        $this->readPosition($channel, $viewer, null);

        $this->client->loginUser($viewer);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', [], 0),
            ],
        ]);
    }

    public function test_one_member_having_read_it_is_named(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $viewer = $this->member($space, 'batteur');
        $silent = $this->member($space, 'guitariste');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $viewer, '2026-09-10 20:01:00');
        $this->readPosition($channel, $silent, null);

        $this->client->loginUser($viewer);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1),
            ],
        ]);
    }

    public function test_every_other_member_having_read_it_names_them_all(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $viewer = $this->member($space, 'batteur');
        $other = $this->member($space, 'guitariste');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $author, '2026-09-10 20:03:00');
        $this->readPosition($channel, $viewer, '2026-09-10 20:01:00');
        $this->readPosition($channel, $other, '2026-09-10 20:02:00');

        $this->client->loginUser($viewer);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                // Alphabetical, so two identical requests name them in the same order.
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur', 'guitariste'], 2),
            ],
        ]);
    }

    public function test_the_author_is_not_a_reader_of_their_own_message(): void
    {
        // Their own read position is past their own message, which is what opening the tab does to it.
        // Writing something is not reading it, so « Vu par » must not name them and must not count them.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $reader = $this->member($space, 'batteur');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $author, '2026-09-10 20:05:00');
        $this->readPosition($channel, $reader, '2026-09-10 20:01:00');

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1, 'on répète mardi'),
            ],
        ]);
    }

    public function test_a_member_who_has_left_is_neither_named_nor_counted(): void
    {
        // Their read-state row survives the departure (#948 concern 3), so without the membership join
        // they would keep reading the band's messages forever. Named before « batteur » alphabetically,
        // so a missing filter shows up as the first name in the list rather than as a count.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $reader = $this->member($space, 'batteur');
        $formerMember = $this->member($space, 'ancienne', MembershipStatus::Left);
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $reader, '2026-09-10 20:01:00');
        $this->readPosition($channel, $formerMember, '2026-09-10 20:01:00');

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1, 'on répète mardi'),
            ],
        ]);
    }

    public function test_a_member_who_has_never_opened_the_channel_is_simply_absent(): void
    {
        // Channel membership is derived from the band space, so a member who has never opened the tab
        // has no read-state row at all. Absent is unread, not missing: the count is of who has read,
        // and the band's roster is the denominator the client counts against.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $reader = $this->member($space, 'batteur');
        $this->member($space, 'guitariste');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $reader, '2026-09-10 20:01:00');

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1, 'on répète mardi'),
            ],
        ]);
    }

    public function test_a_read_position_in_the_same_second_as_the_message_has_read_it(): void
    {
        // Both columns are second granular, so this case is ordinary rather than exotic. At or after
        // is the exact complement of the unread rule the counters use, `creationDatetime >
        // lastReadDatetime`: anything else would have one message count as read and as unread at once.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $sameSecond = $this->member($space, 'batteur');
        $aSecondEarlier = $this->member($space, 'guitariste');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $sameSecond, self::SENT_AT);
        $this->readPosition($channel, $aSecondEarlier, '2026-09-10 19:59:59');

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1, 'on répète mardi'),
            ],
        ]);
    }

    public function test_a_tombstone_reports_nobody(): void
    {
        // Everybody has read it, and it still says nothing: « Vu par » under « Message supprimé » would
        // be about content that no longer exists (#967).
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $reader = $this->member($space, 'batteur');
        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => '',
            'creationDatetime' => new \DateTime(self::SENT_AT),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-10 20:10:00'),
        ])->create();
        $this->readPosition($channel, $reader, '2026-09-10 20:20:00');

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $author, '', [], 0, null, true),
            ],
        ]);
    }

    public function test_the_single_message_an_endpoint_returns_carries_its_readers(): void
    {
        // The item path builds from an entity instead of from the page's projection, so it is a second
        // call site with arguments of its own, and every other expectation in the suite is the empty
        // case. Reacting is the one of those endpoints where the viewer is not the author, which is
        // what makes this able to tell the two apart: the author has read past their own message and
        // must still be left out, the member reacting has read it and must be named.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        $reactor = $this->member($space, 'batteur');
        $message = $this->messageFrom($channel, $author, 'on répète mardi');
        $this->readPosition($channel, $author, '2026-09-10 20:05:00');
        $this->readPosition($channel, $reactor, '2026-09-10 20:01:00');

        $this->client->loginUser($reactor);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages/' . $message->id . '/reactions',
            ['emoji' => 'thumbs_up'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            ...$this->expectedMessage($message, $space, $author, 'on répète mardi', ['batteur'], 1),
            'reactions' => [
                ['key' => 'thumbs_up', 'emoji' => '👍', 'count' => 1, 'has_reacted' => true],
            ],
        ]);
    }

    public function test_the_receipts_cost_one_query_whatever_the_page_holds(): void
    {
        // A read position belongs to a member, not to a message, so twelve messages and five members
        // are one query and not sixty. The total for the request is not what is asserted here: the
        // roster checkMember() hydrates costs three profile selects per member (#730), so it grows with
        // the band whatever this feature does. What must not grow is the lookup this issue adds.
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $author = $this->member($space, 'bassiste');
        foreach (range(1, 4) as $index) {
            $reader = $this->member($space, 'lecteur_' . $index);
            $this->readPosition($channel, $reader, '2026-09-10 20:30:00');
        }

        foreach (range(1, 12) as $minute) {
            MessageFactory::new([
                'thread' => $channel,
                'author' => $author,
                'content' => 'message ' . $minute,
                'creationDatetime' => new \DateTime(sprintf('2026-09-10 20:%02d:00', $minute)),
            ])->create();
        }

        $this->client->loginUser($author);
        $this->client->enableProfiler();
        // The factories above left every user managed, which would hide the very lazy loads this test
        // exists to count.
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $firstMessage = $this->getResponseAsArray()['member'][0];
        $this->assertSame(['lecteur_1', 'lecteur_2', 'lecteur_3', 'lecteur_4'], $firstMessage['read_by_usernames']);
        $this->assertSame(4, $firstMessage['read_count']);

        $this->assertCount(
            1,
            $this->queriesMatching('message_thread_meta'),
            'The read positions must be read once for the whole page, never per message or per member',
        );
    }

    private function url(BandSpace $space): string
    {
        return '/api/band_spaces/' . $space->id . '/chat/messages';
    }

    private function channelOf(BandSpace $space): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($space)->create();
    }

    private function member(BandSpace $space, string $username, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => $username,
            'email' => $username . '@test.com',
        ]);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $user,
            'status' => $status,
            'creationDatetime' => new \DateTime('2026-09-01 09:00:00'),
        ])->create();

        return $user;
    }

    private function messageFrom(MessageThread $channel, User $author, string $content): Message
    {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => $content,
            'creationDatetime' => new \DateTime(self::SENT_AT),
        ])->create();
    }

    private function readPosition(MessageThread $channel, User $user, ?string $lastReadDatetime): void
    {
        MessageThreadMetaFactory::new([
            'thread' => $channel,
            'user' => $user,
            'lastReadDatetime' => $lastReadDatetime === null ? null : new \DateTimeImmutable($lastReadDatetime),
        ])->create();
    }

    /**
     * @param list<string> $readByUsernames
     *
     * @return array<string, mixed>
     */
    private function expectedMessage(
        Message $message,
        BandSpace $space,
        User $author,
        string $content,
        array $readByUsernames,
        int $readCount,
        ?string $editableContent = null,
        bool $isDeleted = false,
    ): array {
        return [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => self::SENT_AT_ISO,
            'reactions' => [],
            'attachments' => [],
            'update_datetime' => null,
            'editable_content' => $editableContent,
            'is_deleted' => $isDeleted,
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
            'read_by_usernames' => $readByUsernames,
            'read_count' => $readCount,
            'image' => null,
        ];
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
