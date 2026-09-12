<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * What a channel does that a direct message does not, all of which was a silent no-op before #960:
 * membership is derived, so every participant-driven step of MessageSenderProcedure had nothing to
 * iterate. See ThreadMemberResolver.
 */
#[ResetDatabase]
class ChatSendPathTest extends ApiTestCase
{
    public function test_every_active_member_gets_a_read_state_row(): void
    {
        // Without this the unread count in #962 would be zero for everyone forever: it counts through
        // MessageThreadMeta, and a member with no row contributes nothing.
        [$space, $channel, $sender, $others, $kicked] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, 'on répète mardi');
        $this->assertResponseIsSuccessful();

        $metaRepository = self::getContainer()->get(MessageThreadMetaRepository::class);
        foreach ([$sender, ...$others] as $member) {
            $this->assertNotNull(
                $metaRepository->findOneBy(['thread' => $channel->id, 'user' => $member->id]),
                sprintf('%s is an active member and should have a read-state row', $member->username),
            );
        }

        $this->assertNull(
            $metaRepository->findOneBy(['thread' => $channel->id, 'user' => $kicked->id]),
            'Somebody who was kicked is not in the conversation any more',
        );
    }

    public function test_a_channel_emails_nobody(): void
    {
        // A direct message to these same people would email them: none was recently active and none
        // has turned notifications off. A channel does not, because MessageSentListener links to the
        // direct message route and one email per member per message is the volume #948 concern 10 is
        // about.
        [$space, , $sender] = $this->band();

        $this->client->loginUser($sender);
        $this->post($space, 'on répète mardi');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
    }

    public function test_the_send_locks_every_member_before_the_thread(): void
    {
        [$space, , $sender, , $kicked] = $this->band();

        $this->client->loginUser($sender);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->post($space, 'on répète mardi');
        $this->assertResponseIsSuccessful();

        $locks = $this->lockQueries();
        $userLock = $this->firstIndexMatching($locks, 'FROM fos_user');
        $threadLock = $this->firstIndexMatching($locks, 'FROM message_thread t0');

        $this->assertNotNull($userLock, 'A channel send must write-lock its members');
        $this->assertLessThan(
            $threadLock,
            $userLock,
            'Members before the thread, or this deadlocks against the direct message path (#957)',
        );

        // Four, not three: the band has three active members and one who was kicked.
        // BandSpaceMemberChecker hydrates every membership's user whatever its status, and a hydrated
        // User makes the flush take its row lock anyway (#985), so the pre-locked set has to cover
        // them or the ordering guarantee above is a fiction.
        $this->assertSame(
            4,
            $this->lockedUserCount(),
            'The locked set must cover every membership, not only the active ones',
        );
    }

    /**
     * @return array{0: BandSpace, 1: MessageThread, 2: User, 3: User[], 4: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $sender])->create();

        $others = [];
        foreach (['bassiste', 'chanteuse'] as $username) {
            $other = UserFactory::new()->asBaseUser()->create([
                'username' => $username,
                'email' => $username . '@test.com',
            ]);
            BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $other])->create();
            $others[] = $other;
        }

        $kicked = UserFactory::new()->asBaseUser()->create(['username' => 'ancien', 'email' => 'ancien@test.com']);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $kicked,
            'status' => MembershipStatus::Kicked,
        ])->create();

        return [$space, MessageThreadFactory::new()->forBandSpace($space)->create(), $sender, $others, $kicked];
    }

    private function post(BandSpace $bandSpace, string $content): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages',
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    /**
     * @return list<string>
     */
    private function lockQueries(): array
    {
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');

        $locks = [];
        foreach ($profile->getCollector('db')->getQueries()['default'] ?? [] as $query) {
            $sql = (string) $query['sql'];
            if (str_contains($sql, 'FOR UPDATE')) {
                $locks[] = $sql;
            }
        }

        return $locks;
    }

    /**
     * How many rows the user lock covers, counted from the placeholders of its `IN (?, ?, ...)`, which
     * is the one part of the statement the profiler reports verbatim.
     */
    private function lockedUserCount(): int
    {
        foreach ($this->lockQueries() as $sql) {
            if (str_contains($sql, 'FROM fos_user')) {
                return substr_count($sql, '?');
            }
        }

        return 0;
    }

    /**
     * @param list<string> $locks
     */
    private function firstIndexMatching(array $locks, string $needle): ?int
    {
        foreach ($locks as $index => $sql) {
            if (str_contains($sql, $needle)) {
                return $index;
            }
        }

        return null;
    }
}
