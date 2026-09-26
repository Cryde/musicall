<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
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
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « X écrit… » (#1040): a signal to the rest of the band, never stored.
 */
#[ResetDatabase]
class ChatTypingSignalTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_typing_signals_the_other_active_members_with_the_typist_id(): void
    {
        [$space, $channel, $typist, $other, $left, $kicked, $elsewhere] = $this->band();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        foreach ([$typist, $left, $kicked, $elsewhere] as $excluded) {
            $this->assertNotContains(MercureTopic::userNotifications((string) $excluded->id), $hub->publishedTopics());
        }
        $this->assertCount(1, $hub->updates);
        $this->assertSame([MercureTopic::userNotifications((string) $other->id)], $hub->updates[0]->getTopics());
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame(
            json_encode([
                'type' => 'band_space_typing',
                'band_space_id' => (string) $space->id,
                'thread_id' => (string) $channel->id,
                'user_id' => (string) $typist->id,
            ], JSON_THROW_ON_ERROR),
            $hub->updates[0]->getData(),
        );
    }

    public function test_a_hub_that_is_down_costs_the_typist_nothing(): void
    {
        [$space, , $typist] = $this->band();
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    public function test_a_non_member_cannot_signal(): void
    {
        [$space] = $this->band();
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($outsider);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
        $this->assertSame([], $hub->updates);
    }

    public function test_typing_past_the_budget_is_refused(): void
    {
        [$space, , $typist] = $this->band();
        // The whole minute's budget spent up front: the request is the forty-first.
        self::getContainer()->get('limiter.chat_typing')->create($typist->getUserIdentifier())->consume(40);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($typist);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/typing', server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
        [$space] = $this->band();

        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/typing');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /**
     * @return array{0: BandSpace, 1: MessageThread, 2: User, 3: User, 4: User, 5: User, 6: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create();

        return [
            $space,
            MessageThreadFactory::new()->forBandSpace($space)->create(),
            $this->member($space, 'batteur'),
            $this->member($space, 'bassiste'),
            $this->member($space, 'ancien', MembershipStatus::Left),
            $this->member($space, 'exclu', MembershipStatus::Kicked),
            $this->member(BandSpaceFactory::new()->create(), 'voisin'),
        ];
    }

    private function member(BandSpace $bandSpace, string $username, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'status' => $status])->create();

        return $user;
    }
}
