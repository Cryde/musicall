<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A block between two users closes their direct conversation both ways, and hides it from the
 * inbox of whoever placed it (#1117). Band Space channels are untouched.
 */
#[ResetDatabase]
class MessageBlockedContactTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_blocker_cannot_start_a_conversation_with_the_blocked_user(): void
    {
        [$blocker, $blocked] = $this->blockedPair();

        $this->client->loginUser($blocker);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $blocked->id,
            'content' => 'bonjour',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertBlockedResponse();
        $this->assertSame(0, self::getContainer()->get(MessageRepository::class)->count());
    }

    public function test_the_blocked_user_cannot_start_a_conversation_with_the_blocker(): void
    {
        [$blocker, $blocked] = $this->blockedPair();

        $this->client->loginUser($blocked);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $blocker->id,
            'content' => 'bonjour',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertBlockedResponse();
        $this->assertSame(0, self::getContainer()->get(MessageRepository::class)->count());
    }

    public function test_nobody_can_write_in_a_conversation_that_existed_before_the_block(): void
    {
        [$blocker, $blocked] = $this->blockedPair();
        $thread = $this->directThread($blocker, $blocked);

        $this->client->loginUser($blocked);
        $this->client->jsonRequest('POST', '/api/messages', [
            'thread' => '/api/message_threads/' . $thread->id,
            'content' => 'tu es là ?',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertBlockedResponse();
        $this->assertSame(1, self::getContainer()->get(MessageRepository::class)->count());
    }

    public function test_the_conversation_leaves_the_blockers_inbox(): void
    {
        [$blocker, $blocked] = $this->blockedPair();
        $this->directThread($blocker, $blocked);

        $this->client->loginUser($blocker);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_the_blocked_user_keeps_the_conversation_in_their_inbox(): void
    {
        [$blocker, $blocked] = $this->blockedPair();
        $thread = $this->directThread($blocker, $blocked);
        $meta = MessageThreadMetaFactory::new([
            'user' => $blocked,
            'thread' => $thread,
            'lastReadDatetime' => new \DateTimeImmutable('2026-09-03 10:00:00'),
        ])->create();
        $participants = $thread->messageParticipants->toArray();
        $message = $thread->lastMessage;
        $this->assertNotNull($message);

        $this->client->loginUser($blocked);
        $this->client->request('GET', '/api/message_thread_metas');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id' => $meta->id,
                    'unread_count' => 0,
                    'thread' => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id' => $thread->id,
                        'message_participants' => array_map(static fn ($participant): array => [
                            '@id' => '/api/message_participants/' . $participant->id,
                            '@type' => 'MessageParticipant',
                            'participant' => [
                                '@id' => '/api/users/' . $participant->participant->id,
                                '@type' => 'User',
                                'id' => $participant->participant->id,
                                'username' => $participant->participant->username,
                                'display_name' => $participant->participant->username,
                            ],
                        ], $participants),
                        'last_message' => [
                            '@id' => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-09-02T10:00:00+00:00',
                            'author' => [
                                '@id' => '/api/users/' . $blocked->id,
                                '@type' => 'User',
                                'id' => $blocked->id,
                                'username' => 'bob_bass',
                                'display_name' => 'bob_bass',
                            ],
                            'content' => 'avant le blocage',
                            'content_preview' => 'avant le blocage',
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_the_badge_drops_the_hidden_conversation_but_keeps_the_shared_band(): void
    {
        [$blocker, $blocked] = $this->blockedPair();
        $this->directThread($blocker, $blocked);
        $band = $this->bandChannelWithUnread($blocker, $blocked);

        $this->client->loginUser($blocker);
        $this->client->request('GET', '/api/notifications');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Notification',
            '@id' => '/api/notifications',
            '@type' => 'Notification',
            // The direct message from the blocked user would make this 2.
            'unread_messages' => 1,
            'band_space_chat_unread' => [(string) $band->id => 1],
        ]);
    }

    /** @return array{User, User} */
    private function blockedPair(): array
    {
        $blocker = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice.drums@example.com']);
        $blocked = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        UserBlockFactory::new()->create(['blocker' => $blocker, 'blocked' => $blocked]);

        return [$blocker, $blocked];
    }

    /** A conversation holding one message from the blocked user, unread by the blocker. */
    private function directThread(User $blocker, User $blocked): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $blocker])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $blocked])->create();
        $message = MessageFactory::new([
            'author' => $blocked,
            'thread' => $thread,
            'content' => 'avant le blocage',
            'creationDatetime' => new \DateTime('2026-09-02 10:00:00'),
        ])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
        MessageThreadMetaFactory::new(['user' => $blocker, 'thread' => $thread, 'lastReadDatetime' => null])->create();

        return $thread;
    }

    private function bandChannelWithUnread(User $member, User $writer): BandSpace
    {
        $band = BandSpaceFactory::new()->create();
        foreach ([$member, $writer] as $user) {
            BandSpaceMembershipFactory::new([
                'bandSpace' => $band,
                'user' => $user,
                'creationDatetime' => new \DateTime('2026-09-01 09:00:00'),
            ])->create();
        }
        $channel = MessageThreadFactory::new()->forBandSpace($band)->create();
        MessageFactory::new([
            'thread' => $channel,
            'author' => $writer,
            'creationDatetime' => new \DateTime('2026-09-02 11:00:00'),
        ])->create();
        MessageThreadMetaFactory::new(['thread' => $channel, 'user' => $member, 'lastReadDatetime' => null])->create();

        return $band;
    }

    private function assertBlockedResponse(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Vous ne pouvez pas échanger de messages avec cet utilisateur',
            'description' => 'Vous ne pouvez pas échanger de messages avec cet utilisateur',
        ]);
    }
}
