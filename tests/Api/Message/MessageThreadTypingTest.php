<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « X est en train d'écrire » in a direct conversation (#1242): a signal to the other participant,
 * never stored.
 */
#[ResetDatabase]
class MessageThreadTypingTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_typing_signals_the_other_participant_with_the_typist_id(): void
    {
        $typist = $this->user('alice_drums');
        $other = $this->user('bob_bass');
        $thread = $this->directThread($typist, $other);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $other->id)], $hub->updates[0]->getTopics());
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame(
            json_encode([
                'type' => 'message_typing',
                'thread_id' => (string) $thread->id,
                'user_id' => (string) $typist->id,
            ], JSON_THROW_ON_ERROR),
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_deleted_participant_is_not_signalled(): void
    {
        $typist = $this->user('alice_drums');
        $deleted = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_user',
            'email' => 'deleted@test.com',
            'deletionDatetime' => new \DateTimeImmutable(),
        ]);
        $thread = $this->directThread($typist, $deleted);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([], $hub->updates);
    }

    public function test_a_hub_that_is_down_costs_the_typist_nothing(): void
    {
        $typist = $this->user('alice_drums');
        $thread = $this->directThread($typist, $this->user('bob_bass'));
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    public function test_a_stranger_to_the_conversation_gets_a_404(): void
    {
        $thread = $this->directThread($this->user('alice_drums'), $this->user('bob_bass'));
        $stranger = $this->user('intrus');
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($stranger);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertThreadNotFound();
        $this->assertSame([], $hub->updates);
    }

    public function test_an_unknown_conversation_gets_a_404(): void
    {
        $user = $this->user('alice_drums');

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/message_threads/0198f0a0-0000-7000-8000-000000000000/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertThreadNotFound();
    }

    public function test_a_band_channel_is_refused_even_to_its_members(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        $member = $this->user('batteur');
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member, 'status' => MembershipStatus::Active])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($member);
        $this->client->request('POST', '/api/message_threads/' . $channel->id . '/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertThreadNotFound();
        $this->assertSame([], $hub->updates);
    }

    public function test_the_blocked_user_cannot_signal(): void
    {
        $blocker = $this->user('alice_drums');
        $blocked = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $blocker, 'blocked' => $blocked]);
        $thread = $this->directThread($blocker, $blocked);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($blocked);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
        $this->assertSame([], $hub->updates);
    }

    public function test_the_blocker_cannot_signal_either(): void
    {
        $blocker = $this->user('alice_drums');
        $blocked = $this->user('bob_bass');
        UserBlockFactory::new()->create(['blocker' => $blocker, 'blocked' => $blocked]);
        $thread = $this->directThread($blocker, $blocked);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($blocker);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
        $this->assertSame([], $hub->updates);
    }

    public function test_typing_past_the_budget_is_refused(): void
    {
        $typist = $this->user('alice_drums');
        $thread = $this->directThread($typist, $this->user('bob_bass'));
        // The whole minute's budget spent up front, as the band chat shares it: the request is the forty-first.
        self::getContainer()->get('limiter.chat_typing')->create($typist->getUserIdentifier())->consume(40);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
        $this->assertSame([], $hub->updates);
    }

    public function test_not_logged(): void
    {
        $thread = $this->directThread($this->user('alice_drums'), $this->user('bob_bass'));

        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    private function assertThreadNotFound(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Thread not found.',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Thread not found.',
        ]);
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
}
