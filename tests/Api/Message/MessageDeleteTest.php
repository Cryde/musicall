<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageContactOriginFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Deleting your own message in a direct conversation (#1245): a tombstone, author only.
 */
#[ResetDatabase]
class MessageDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);

        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_author_leaves_a_tombstone(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $deleted = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertNotNull($deleted, 'A tombstone keeps the row');
        $this->assertTrue($deleted->isDeleted());
        $this->assertSame('', $deleted->content);
    }

    public function test_the_tombstone_reads_as_deleted_without_its_origin_card(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-10-21 08:00:00'),
        ])->create();
        MessageContactOriginFactory::new(['message' => $message])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($reader);
        $this->client->request('GET', '/api/messages/' . $thread->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
            '@id' => '/api/messages/' . $thread->id,
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/messages/' . $messageId,
                    '@type' => 'Message',
                    'id' => $messageId,
                    'creation_datetime' => '2026-10-20T20:00:00+00:00',
                    'author' => [
                        '@id' => '/api/users/' . $author->id,
                        '@type' => 'User',
                        'id' => $author->id,
                        'username' => 'alice_drums',
                        'display_name' => 'alice_drums',
                    ],
                    'content' => '',
                    'is_seen' => false,
                    'is_deleted' => true,
                    'reactions' => [],
                ],
            ],
            'totalItems' => 1,
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/messages/' . $thread->id . '{?order[creation_datetime],order[id]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[creation_datetime]',
                        'property' => 'creation_datetime',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[id]',
                        'property' => 'id',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_the_inbox_row_keeps_nothing_of_a_deleted_message(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-10-21 08:00:00'),
        ])->create();
        MessageContactOriginFactory::new(['message' => $message])->create();
        $thread->lastMessage = $message;
        \Zenstruck\Foundry\Persistence\save($thread);
        $meta = MessageThreadMetaFactory::new([
            'thread' => $thread,
            'user' => $reader,
            'lastReadDatetime' => new \DateTimeImmutable('2026-10-21 09:00:00'),
        ])->create();
        $participants = $thread->messageParticipants->toArray();

        $this->client->loginUser($reader);
        $this->client->request('GET', '/api/message_thread_metas', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadMeta',
            '@id' => '/api/message_thread_metas',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/message_thread_metas/' . $meta->id,
                    '@type' => 'MessageThreadMeta',
                    'id' => (string) $meta->id,
                    'unread_count' => 0,
                    'thread' => [
                        '@id' => '/api/message_threads/' . $thread->id,
                        '@type' => 'MessageThread',
                        'id' => (string) $thread->id,
                        'message_participants' => [
                            [
                                '@id' => '/api/message_participants/' . $participants[0]->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $author->id,
                                    '@type' => 'User',
                                    'id' => $author->id,
                                    'username' => 'alice_drums',
                                    'display_name' => 'alice_drums',
                                ],
                            ],
                            [
                                '@id' => '/api/message_participants/' . $participants[1]->id,
                                '@type' => 'MessageParticipant',
                                'participant' => [
                                    '@id' => '/api/users/' . $reader->id,
                                    '@type' => 'User',
                                    'id' => $reader->id,
                                    'username' => 'bob_bass',
                                    'display_name' => 'bob_bass',
                                ],
                            ],
                        ],
                        'last_message' => [
                            '@id' => '/api/messages/' . $message->id,
                            '@type' => 'Message',
                            'creation_datetime' => '2026-10-20T20:00:00+00:00',
                            'author' => [
                                '@id' => '/api/users/' . $author->id,
                                '@type' => 'User',
                                'id' => $author->id,
                                'username' => 'alice_drums',
                                'display_name' => 'alice_drums',
                            ],
                            'content' => '',
                            'content_preview' => 'Message supprimé',
                        ],
                        'can_send_media' => true,
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_the_other_participant_cannot_delete_it(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author);
        $messageId = (string) $message->id;

        $this->client->loginUser($other);
        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => "Seul l'auteur peut supprimer ce message",
            'description' => "Seul l'auteur peut supprimer ce message",
        ]);
        $this->assertFalse(self::getContainer()->get(MessageRepository::class)->find($messageId)->isDeleted());
    }

    public function test_deleting_twice_is_a_miss(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => '',
            'deletionDatetime' => new \DateTimeImmutable('2026-10-21 08:00:00'),
        ])->create();

        $this->client->loginUser($author);
        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
        ]);
    }

    public function test_a_stranger_to_the_conversation_gets_a_404(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);

        $this->client->loginUser($this->user('intrus'));
        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Thread not found.',
            'description' => 'Thread not found.',
        ]);
    }

    public function test_a_blocked_pair_cannot_delete(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $author, 'blocked' => $other]);
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author);

        $this->client->loginUser($author);
        $this->delete($thread, $message);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 403,
            'type' => '/errors/403',
            'detail' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
            'description' => 'Tu ne peux pas échanger de messages avec cet utilisateur',
        ]);
    }

    private function delete(MessageThread $thread, Message $message): void
    {
        $this->client->request(
            'DELETE',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id,
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );
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

    private function message(MessageThread $thread, User $author): Message
    {
        return MessageFactory::new([
            'thread' => $thread,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-10-20 20:00:00'),
        ])->create();
    }
}
