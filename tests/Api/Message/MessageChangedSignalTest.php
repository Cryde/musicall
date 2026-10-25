<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The live signal behind an edit, a delete or a reaction in a direct conversation (#1245): a tag on
 * both participants' private topics, the actor included, never the content.
 */
#[ResetDatabase]
class MessageChangedSignalTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_an_edit_signals_both_participants(): void
    {
        $author = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($author, $other);
        $message = $this->message($thread, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($author);
        $this->client->request(
            'PATCH',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id,
            server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(['content' => 'on répète mercredi'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $hub->updates);
        $this->assertEqualsCanonicalizing(
            [MercureTopic::userNotifications((string) $author->id), MercureTopic::userNotifications((string) $other->id)],
            $hub->updates[0]->getTopics(),
        );
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame(
            json_encode([
                'type' => 'message_changed',
                'thread_id' => (string) $thread->id,
                'message_id' => (string) $message->id,
                'change' => 'edit',
            ], JSON_THROW_ON_ERROR),
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_delete_signals_its_change(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($author);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
        $this->assertSame(
            json_encode([
                'type' => 'message_changed',
                'thread_id' => (string) $thread->id,
                'message_id' => (string) $message->id,
                'change' => 'delete',
            ], JSON_THROW_ON_ERROR),
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_reaction_signals_its_change(): void
    {
        $author = $this->user('alice_drums');
        $reader = $this->user('bob_bass');
        $thread = $this->directThread($author, $reader);
        $message = $this->message($thread, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($reader);
        $this->client->request(
            'POST',
            '/api/message_threads/' . $thread->id . '/messages/' . $message->id . '/reactions',
            server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(['emoji' => 'fire'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $hub->updates);
        $this->assertSame(
            json_encode([
                'type' => 'message_changed',
                'thread_id' => (string) $thread->id,
                'message_id' => (string) $message->id,
                'change' => 'reaction',
            ], JSON_THROW_ON_ERROR),
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_deleted_participant_is_not_signalled(): void
    {
        $author = $this->user('alice_drums');
        $deleted = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_user',
            'email' => 'deleted@test.com',
            'deletionDatetime' => new \DateTimeImmutable(),
        ]);
        $thread = $this->directThread($author, $deleted);
        $message = $this->message($thread, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($author);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $author->id)], $hub->updates[0]->getTopics());
    }

    public function test_a_hub_that_is_down_costs_the_edit_nothing(): void
    {
        $author = $this->user('alice_drums');
        $thread = $this->directThread($author, $this->user('bob_bass'));
        $message = $this->message($thread, $author);
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($author);
        $this->client->request('DELETE', '/api/message_threads/' . $thread->id . '/messages/' . $message->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
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
