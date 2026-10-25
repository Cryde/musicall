<?php

declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « En ligne » in a direct conversation (#1246): the band chat's heartbeat, for two people.
 */
#[ResetDatabase]
class MessageThreadPresenceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string TAB = 'onglet-un';
    private const string OTHER_TAB = 'onglet-deux';

    public function test_not_logged(): void
    {
        [$thread] = $this->conversation();

        $this->beat($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_heartbeat_answers_with_the_other_person_and_announces_the_arrival(): void
    {
        [$thread, $me, $other] = $this->conversation();
        $this->markOnline($thread, $other);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [(string) $other->id],
        ]);
        $this->assertTrue($this->isOnline($thread, $me));
        // To the other person only, and with nobody in the tag.
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $other->id)], $hub->updates[0]->getTopics());
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame('{"type":"message_presence","thread_id":"' . $thread->id . '"}', $hub->updates[0]->getData());
    }

    public function test_the_other_person_away_is_an_empty_answer(): void
    {
        [$thread, $me] = $this->conversation();

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_presence_in_another_conversation_does_not_count(): void
    {
        [$thread, $me, $other] = $this->conversation();
        [$elsewhere] = $this->conversation('carla_keys', 'other_bass');
        $this->markOnline($elsewhere, $other);

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_a_second_tab_is_not_announced_again(): void
    {
        [$thread, $me] = $this->conversation();
        $this->markOnline($thread, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($thread, self::OTHER_TAB);

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing([self::TAB, self::OTHER_TAB], $this->tabsOf($thread, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_the_other_person_who_opted_out_is_never_in_the_answer(): void
    {
        [$thread, $me, $other] = $this->conversation();
        $this->optOut($other);
        $this->markOnline($thread, $other);

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_opting_out_is_never_marked_and_announces_nothing(): void
    {
        [$thread, $me] = $this->conversation();
        $this->optOut($me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->isOnline($thread, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_a_deleted_account_is_never_in_the_answer_nor_told(): void
    {
        [$thread, $me, $other] = $this->conversation();
        $this->markOnline($thread, $other);
        $other->deletionDatetime = new \DateTimeImmutable();
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [],
        ]);
        $this->assertTrue($this->isOnline($thread, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_a_tab_whose_heartbeats_stopped_no_longer_counts(): void
    {
        [$thread, $me, $other] = $this->conversation();
        $cache = self::getContainer()->get(CacheItemPoolInterface::class);
        $cache->save($cache->getItem($this->key($thread, $other))->set([self::TAB => time() - 1])->expiresAfter(75));

        $this->client->loginUser($me);
        $this->beat($thread);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MessageThreadPresence',
            '@id' => '/api/message_threads/' . $thread->id . '/presence',
            '@type' => 'MessageThreadPresence',
            'thread_id' => (string) $thread->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_leaving_takes_me_out_and_says_so(): void
    {
        [$thread, $me, $other] = $this->conversation();
        $this->markOnline($thread, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertFalse($this->isOnline($thread, $me));
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $other->id)], $hub->updates[0]->getTopics());
        $this->assertSame('{"type":"message_presence","thread_id":"' . $thread->id . '"}', $hub->updates[0]->getData());
    }

    public function test_closing_one_of_two_tabs_keeps_me_online_silently(): void
    {
        [$thread, $me] = $this->conversation();
        $this->markOnline($thread, $me, [self::TAB, self::OTHER_TAB]);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([self::OTHER_TAB], $this->tabsOf($thread, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_leaving_when_not_here_says_nothing(): void
    {
        [$thread, $me] = $this->conversation();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([], $hub->updates);
    }

    public function test_leaving_is_not_held_back_by_a_spent_budget(): void
    {
        [$thread, $me] = $this->conversation();
        $this->markOnline($thread, $me);
        self::getContainer()->get('limiter.chat_presence')->create($me->getUserIdentifier())->consume(20);

        $this->client->loginUser($me);
        $this->leave($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertFalse($this->isOnline($thread, $me));
    }

    public function test_leaving_not_logged(): void
    {
        [$thread] = $this->conversation();

        $this->leave($thread);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_leaving_with_a_malformed_tab_is_refused(): void
    {
        [$thread, $me] = $this->conversation();

        $this->client->loginUser($me);
        $this->leave($thread, 'x');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tab',
                    'message' => 'Identifiant d\'onglet invalide',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => 'tab: Identifiant d\'onglet invalide',
            'description' => 'tab: Identifiant d\'onglet invalide',
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_stranger_cannot_leave_somebody_else_s_conversation(): void
    {
        [$thread, , $other] = $this->conversation();
        $this->markOnline($thread, $other);

        $this->client->loginUser($this->user('intrus'));
        $this->leave($thread);

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
        $this->assertTrue($this->isOnline($thread, $other));
    }

    public function test_leaving_under_a_block_is_refused_and_says_nothing(): void
    {
        [$thread, $me, $other] = $this->conversation();
        UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $me]);
        $this->markOnline($thread, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($thread);

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

    public function test_a_malformed_tab_is_refused(): void
    {
        [$thread, $me] = $this->conversation();

        $this->client->loginUser($me);
        $this->beat($thread, 'x');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tab',
                    'message' => 'Identifiant d\'onglet invalide',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => 'tab: Identifiant d\'onglet invalide',
            'description' => 'tab: Identifiant d\'onglet invalide',
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_stranger_to_the_conversation_gets_a_404(): void
    {
        [$thread] = $this->conversation();

        $this->client->loginUser($this->user('intrus'));
        $this->beat($thread);

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

    public function test_a_band_channel_has_its_own_presence(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        $me = $this->user('alice_drums');
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $me])->create();

        $this->client->loginUser($me);
        $this->beat($channel);

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

    public function test_blocked_by_the_other_person_no_presence(): void
    {
        [$thread, $me, $other] = $this->conversation();
        UserBlockFactory::new()->create(['blocker' => $other, 'blocked' => $me]);
        $this->markOnline($thread, $other);

        $this->client->loginUser($me);
        $this->beat($thread);

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
        $this->assertFalse($this->isOnline($thread, $me));
    }

    public function test_having_blocked_the_other_person_no_presence(): void
    {
        [$thread, $me, $other] = $this->conversation();
        UserBlockFactory::new()->create(['blocker' => $me, 'blocked' => $other]);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($thread);

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

    public function test_heartbeats_past_the_budget_are_refused(): void
    {
        [$thread, $me] = $this->conversation();
        self::getContainer()->get('limiter.chat_presence')->create($me->getUserIdentifier())->consume(20);

        $this->client->loginUser($me);
        $this->beat($thread);

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
    }

    /** @return array{0: MessageThread, 1: User, 2: User} */
    private function conversation(string $me = 'alice_drums', string $other = 'bob_bass'): array
    {
        $first = $this->user($me);
        $second = $this->user($other);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $first])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $second])->create();

        return [$thread, $first, $second];
    }

    private function user(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    private function optOut(User $user): void
    {
        $preference = new UserNotificationPreference();
        $preference->user = $user;
        $preference->showOnlinePresence = false;
        $user->notificationPreference = $preference;
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($preference);
        $entityManager->flush();
    }

    /** @param list<string> $tabs */
    private function markOnline(MessageThread $thread, User $user, array $tabs = [self::TAB]): void
    {
        $cache = self::getContainer()->get(CacheItemPoolInterface::class);
        $cache->save($cache->getItem($this->key($thread, $user))->set(array_fill_keys($tabs, time() + 75))->expiresAfter(75));
    }

    /** @return list<string> */
    private function tabsOf(MessageThread $thread, User $user): array
    {
        $item = self::getContainer()->get(CacheItemPoolInterface::class)->getItem($this->key($thread, $user));

        return $item->isHit() ? array_keys($item->get()) : [];
    }

    private function isOnline(MessageThread $thread, User $user): bool
    {
        return $this->tabsOf($thread, $user) !== [];
    }

    private function key(MessageThread $thread, User $user): string
    {
        return 'chat_presence_' . $thread->id . '_' . $user->id;
    }

    private function beat(MessageThread $thread, string $tab = self::TAB): void
    {
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/presence?tab=' . urlencode($tab), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    }

    private function leave(MessageThread $thread, string $tab = self::TAB): void
    {
        $this->client->request('POST', '/api/message_threads/' . $thread->id . '/presence/leave?tab=' . urlencode($tab));
    }
}
