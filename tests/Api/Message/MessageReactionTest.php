<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\Message\MessageReactionEmoji;
use App\Repository\Message\MessageReactionRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageReactionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Reacting to a message of a direct conversation (#1245): any message, either person.
 */
#[ResetDatabase]
class MessageReactionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);

        $this->react($thread, $message, 'heart');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_other_participant_reacts_and_gets_the_message_back(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $author, 'emoji' => MessageReactionEmoji::Heart])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($reader);
        $this->react($thread, $message, 'heart');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
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
            'content' => 'on répète mardi',
            'is_seen' => false,
            'is_deleted' => false,
            'reactions' => [
                ['key' => 'heart', 'emoji' => '❤️', 'count' => 2, 'has_reacted' => true],
            ],
        ]);
        $this->assertSame(2, $this->countReactions());
    }

    public function test_reacting_twice_is_a_silent_no_op(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $reader, 'emoji' => MessageReactionEmoji::Heart])->create();
        $messageId = (string) $message->id;
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->react($thread, $message, 'heart');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Message',
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
            'content' => 'on répète mardi',
            'is_seen' => false,
            'is_deleted' => false,
            'reactions' => [
                ['key' => 'heart', 'emoji' => '❤️', 'count' => 1, 'has_reacted' => true],
            ],
        ]);
        $this->assertSame(1, $this->countReactions());
        $this->assertSame([], $hub->updates);
    }

    public function test_an_unknown_emoji_is_refused(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);

        $this->client->loginUser($author);
        $this->react($thread, $message, 'aubergine');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'emoji',
                    'message' => 'Réaction inconnue',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'emoji: Réaction inconnue',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'emoji: Réaction inconnue',
        ]);
        $this->assertSame(0, $this->countReactions());
    }

    public function test_a_stranger_cannot_react(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);

        $this->client->loginUser($this->user('intrus'));
        $this->react($thread, $message, 'heart');

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
        $this->assertSame(0, $this->countReactions());
    }

    public function test_a_blocked_pair_cannot_react(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $author, 'blocked' => $reader]);
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);

        $this->client->loginUser($reader);
        $this->react($thread, $message, 'heart');

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
        $this->assertSame(0, $this->countReactions());
    }

    public function test_a_member_takes_back_their_own_reaction_only(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $reader, 'emoji' => MessageReactionEmoji::Heart])->create();
        MessageReactionFactory::new(['message' => $message, 'user' => $author, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($reader);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions/heart');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $remaining = self::getContainer()->get(MessageReactionRepository::class)->findAll();
        $this->assertCount(1, $remaining);
        $this->assertSame((string) $author->id, (string) $remaining[0]->user->id);
    }

    public function test_taking_back_a_reaction_never_left_is_a_404(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $author, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($reader);
        $this->client->request(
            'DELETE',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions/heart',
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Réaction introuvable',
            'description' => 'Réaction introuvable',
        ]);
        $this->assertSame(1, $this->countReactions());
    }

    public function test_a_stranger_cannot_take_back_a_reaction(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $author, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($this->user('intrus'));
        $this->client->request(
            'DELETE',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions/heart',
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

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
        $this->assertSame(1, $this->countReactions());
    }

    public function test_a_blocked_pair_cannot_take_back_a_reaction(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $author, 'blocked' => $reader]);
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $reader, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($reader);
        $this->client->request(
            'DELETE',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions/heart',
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

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
        $this->assertSame(1, $this->countReactions());
    }

    public function test_the_list_counts_the_reactions_of_the_page(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $author, 'emoji' => MessageReactionEmoji::Guitar])->create();
        MessageReactionFactory::new(['message' => $message, 'user' => $reader, 'emoji' => MessageReactionEmoji::ThumbsUp])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
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
                    'content' => 'on répète mardi',
                    'is_seen' => false,
                    'is_deleted' => false,
                    // In the palette's order, whatever order they were left in.
                    'reactions' => [
                        ['key' => 'thumbs_up', 'emoji' => '👍', 'count' => 1, 'has_reacted' => false],
                        ['key' => 'guitar', 'emoji' => '🎸', 'count' => 1, 'has_reacted' => true],
                    ],
                    'editable_content' => 'on répète mardi',
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

    private function react(MessageThread $thread, Message $message, string $emoji): void
    {
        $this->client->request(
            'POST',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions',
            server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(['emoji' => $emoji], JSON_THROW_ON_ERROR),
        );
    }

    private function countReactions(): int
    {
        return self::getContainer()->get(MessageReactionRepository::class)->count([]);
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
