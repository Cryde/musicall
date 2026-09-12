<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
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

#[ResetDatabase]
class ChatReadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_reading_the_channel_clears_the_count(): void
    {
        [$member, $writer, $space] = $this->band();
        $channel = $this->channelWithAnUnreadMessage($space, $member, $writer);

        $messageRepository = self::getContainer()->get(MessageRepository::class);
        $this->assertSame(
            [(string) $space->id => 1],
            $messageRepository->countUnreadChannelsForUser($member),
            'The fixture is pointless unless it starts unread',
        );

        $this->client->loginUser($member);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $messageRepository->countUnreadChannelsForUser($member));
        $this->assertNotNull($channel->id);
    }

    public function test_a_non_member_cannot_mark_the_channel_read(): void
    {
        [$member, $writer, $space] = $this->band();
        $this->channelWithAnUnreadMessage($space, $member, $writer);
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);

        $this->client->loginUser($stranger);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_a_band_space_that_does_not_exist_is_a_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/band_spaces/3f2504e0-4f89-11d3-9a0c-0305e82c3301/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Band Space introuvable',
            'description' => 'Band Space introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_space_pending_deletion_can_still_be_marked_read(): void
    {
        // The one place in the Band Space where a POST is deliberately not guarded against the
        // deletion grace period, which is why ChatReadProcessor is on BandSpaceWriteGuardCoverageTest's
        // allow list. Reads stay open for those 30 days, so being unable to stop the badge insisting
        // there is something new would be the odd behaviour, not this.
        [$member, $writer, $space] = $this->band(new \DateTimeImmutable('+30 days'));
        $this->channelWithAnUnreadMessage($space, $member, $writer);

        $this->client->loginUser($member);
        $this->client->request('POST', '/api/band_spaces/' . $space->id . '/chat/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(
            [],
            self::getContainer()->get(MessageRepository::class)->countUnreadChannelsForUser($member),
        );
    }

    /**
     * @return array{0: User, 1: User, 2: BandSpace}
     */
    private function band(?\DateTimeImmutable $deletionScheduledFor = null): array
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $writer = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);

        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => $deletionScheduledFor]);
        foreach ([$member, $writer] as $user) {
            BandSpaceMembershipFactory::new([
                'bandSpace' => $space,
                'user' => $user,
                'creationDatetime' => new \DateTime('2026-09-01 09:00:00'),
            ])->create();
        }

        return [$member, $writer, $space];
    }

    private function channelWithAnUnreadMessage(BandSpace $space, User $member, User $writer): MessageThread
    {
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        MessageFactory::new([
            'thread' => $channel,
            'author' => $writer,
            'creationDatetime' => new \DateTime('2026-09-02 10:00:00'),
        ])->create();
        MessageThreadMetaFactory::new(['thread' => $channel, 'user' => $member, 'lastReadDatetime' => null])->create();

        return $channel;
    }
}
