<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Enum\BandSpace\MembershipStatus;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « En ligne » in the band chat (#1040): a heartbeat that answers with who else is there.
 */
#[ResetDatabase]
class ChatPresenceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string TAB = 'onglet-un';
    private const string OTHER_TAB = 'onglet-deux';

    public function test_a_heartbeat_answers_with_the_others_online_and_announces_an_arrival(): void
    {
        [$space, $me, $bassist, $singer] = $this->band();
        $this->markOnline($space, $bassist);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatPresence',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/presence',
            '@type' => 'ChatPresence',
            'band_space_id' => (string) $space->id,
            'online_user_ids' => [(string) $bassist->id],
        ]);
        $this->assertTrue($this->isOnline($space, $me));
        // An arrival: the others' open chats ask again now. Not to me, and no member in the tag.
        $this->assertCount(1, $hub->updates);
        $this->assertEqualsCanonicalizing(
            [MercureTopic::userNotifications((string) $bassist->id), MercureTopic::userNotifications((string) $singer->id)],
            $hub->updates[0]->getTopics(),
        );
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame('{"type":"band_space_presence","band_space_id":"' . $space->id . '"}', $hub->updates[0]->getData());
    }

    public function test_a_member_already_here_is_not_announced_again(): void
    {
        [$space, $me] = $this->band();
        $this->markOnline($space, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $hub->updates);
    }

    public function test_a_member_who_opted_out_is_never_in_the_answer(): void
    {
        [$space, $me, $bassist] = $this->band();
        $this->optOut($bassist);
        $this->markOnline($space, $bassist);

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatPresence',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/presence',
            '@type' => 'ChatPresence',
            'band_space_id' => (string) $space->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_a_member_who_opted_out_is_not_marked_and_announces_nothing(): void
    {
        [$space, $me] = $this->band();
        $this->optOut($me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->isOnline($space, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_a_member_who_left_is_not_in_the_answer(): void
    {
        [$space, $me] = $this->band();
        $left = $this->member($space, 'ancien', MembershipStatus::Left);
        $this->markOnline($space, $left);

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatPresence',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/presence',
            '@type' => 'ChatPresence',
            'band_space_id' => (string) $space->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_leaving_takes_the_member_out_and_says_so(): void
    {
        [$space, $me, $bassist] = $this->band();
        $this->markOnline($space, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($space);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertFalse($this->isOnline($space, $me));
        $this->assertCount(1, $hub->updates);
        $this->assertContains(MercureTopic::userNotifications((string) $bassist->id), $hub->updates[0]->getTopics());
    }

    public function test_leaving_when_not_here_says_nothing(): void
    {
        [$space, $me] = $this->band();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($space);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_second_tab_of_a_member_already_here_is_not_announced(): void
    {
        [$space, $me] = $this->band();
        $this->markOnline($space, $me);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->beat($space, self::OTHER_TAB);

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing([self::TAB, self::OTHER_TAB], $this->tabsOf($space, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_closing_one_of_two_tabs_keeps_the_member_online_silently(): void
    {
        [$space, $me] = $this->band();
        $this->markOnline($space, $me, [self::TAB, self::OTHER_TAB]);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($me);
        $this->leave($space);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([self::OTHER_TAB], $this->tabsOf($space, $me));
        $this->assertSame([], $hub->updates);
    }

    public function test_a_tab_whose_heartbeats_stopped_no_longer_counts(): void
    {
        [$space, $me, $bassist] = $this->band();
        $cache = self::getContainer()->get(CacheItemPoolInterface::class);
        // The entry outlives this tab because another tab kept beating until it closed without a word.
        $cache->save($cache->getItem($this->key($space, $bassist))->set([self::TAB => time() - 1])->expiresAfter(75));

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatPresence',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/presence',
            '@type' => 'ChatPresence',
            'band_space_id' => (string) $space->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_a_deleted_account_is_never_in_the_answer(): void
    {
        [$space, $me, $bassist] = $this->band();
        $this->markOnline($space, $bassist);
        $bassist->deletionDatetime = new \DateTimeImmutable();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($me);
        $this->beat($space);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatPresence',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/presence',
            '@type' => 'ChatPresence',
            'band_space_id' => (string) $space->id,
            'online_user_ids' => [],
        ]);
    }

    public function test_a_malformed_tab_is_refused(): void
    {
        [$space, $me] = $this->band();

        $this->client->loginUser($me);
        $this->beat($space, 'x');

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

    public function test_a_non_member_cannot_beat(): void
    {
        [$space] = $this->band();
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);

        $this->client->loginUser($outsider);
        $this->beat($space);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    public function test_heartbeats_past_the_budget_are_refused(): void
    {
        [$space, $me] = $this->band();
        self::getContainer()->get('limiter.chat_presence')->create($me->getUserIdentifier())->consume(20);

        $this->client->loginUser($me);
        $this->beat($space);

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

    /** @return array{0: BandSpace, 1: User, 2: User, 3: User} */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create();
        MessageThreadFactory::new()->forBandSpace($space)->create();

        return [$space, $this->member($space, 'batteur'), $this->member($space, 'bassiste'), $this->member($space, 'chanteuse')];
    }

    private function member(BandSpace $bandSpace, string $username, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'status' => $status])->create();

        return $user;
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
    private function markOnline(BandSpace $space, User $user, array $tabs = [self::TAB]): void
    {
        $cache = self::getContainer()->get(CacheItemPoolInterface::class);
        $cache->save($cache->getItem($this->key($space, $user))->set(array_fill_keys($tabs, time() + 75))->expiresAfter(75));
    }

    /** @return list<string> */
    private function tabsOf(BandSpace $space, User $user): array
    {
        $item = self::getContainer()->get(CacheItemPoolInterface::class)->getItem($this->key($space, $user));

        return $item->isHit() ? array_keys($item->get()) : [];
    }

    private function isOnline(BandSpace $space, User $user): bool
    {
        return $this->tabsOf($space, $user) !== [];
    }

    private function key(BandSpace $space, User $user): string
    {
        return 'chat_presence_' . $space->id . '_' . $user->id;
    }

    private function beat(BandSpace $space, string $tab = self::TAB): void
    {
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/presence?tab=' . urlencode($tab), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    }

    private function leave(BandSpace $space, string $tab = self::TAB): void
    {
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/presence/leave?tab=' . urlencode($tab));
    }
}
