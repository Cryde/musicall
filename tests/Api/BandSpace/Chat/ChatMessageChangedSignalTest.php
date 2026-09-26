<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Message\MessageReactionEmoji;
use App\Mercure\MercureTopic;
use App\Repository\Message\MessageReactionRepository;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageReactionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The live signal for a message that changed in place (#1056): a reaction, an edit, a delete, a pin.
 * Only who hears it and what it says are asserted here; each endpoint's own test pins its answer.
 */
#[ResetDatabase]
class ChatMessageChangedSignalTest extends ApiTestCase
{
    public function test_a_reaction_signals_every_active_member_with_a_tag(): void
    {
        [$space, $channel, $actor, $author, $left, $kicked, $elsewhere] = $this->band();
        $message = $this->message($channel, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        foreach ([$left, $kicked, $elsewhere] as $excluded) {
            $this->assertNotContains(MercureTopic::userNotifications((string) $excluded->id), $hub->publishedTopics());
        }
        $this->assertCount(1, $hub->updates);
        // The actor too, so their other devices follow.
        $this->assertEqualsCanonicalizing(
            [MercureTopic::userNotifications((string) $actor->id), MercureTopic::userNotifications((string) $author->id)],
            $hub->updates[0]->getTopics(),
        );
        $this->assertTrue($hub->updates[0]->isPrivate());
        $this->assertSame($this->tag($space, $channel, $message, 'reaction'), $hub->updates[0]->getData());
    }

    public function test_a_reaction_already_given_signals_nothing(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $actor, 'emoji' => MessageReactionEmoji::ThumbsUp])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame([], $hub->updates);
    }

    public function test_taking_a_reaction_back_signals(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author);
        MessageReactionFactory::new(['message' => $message, 'user' => $actor, 'emoji' => MessageReactionEmoji::Heart])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('DELETE', $this->messageUrl($space, $message) . '/reactions/heart');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
        $this->assertSame($this->tag($space, $channel, $message, 'reaction'), $hub->updates[0]->getData());
    }

    public function test_an_edit_signals(): void
    {
        [$space, $channel, $actor] = $this->band();
        $message = $this->message($channel, $actor);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->edit($space, $message, 'on répète mercredi');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $hub->updates);
        $this->assertSame($this->tag($space, $channel, $message, 'edit'), $hub->updates[0]->getData());
    }

    public function test_an_edit_that_changes_nothing_signals_nothing(): void
    {
        [$space, $channel, $actor] = $this->band();
        $message = $this->message($channel, $actor);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->edit($space, $message, 'on répète mardi');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $hub->updates);
    }

    public function test_an_edit_refused_signals_nothing(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->edit($space, $message, 'pas mon message');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_delete_signals(): void
    {
        [$space, $channel, $actor] = $this->band();
        $message = $this->message($channel, $actor);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('DELETE', $this->messageUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(1, $hub->updates);
        $this->assertSame($this->tag($space, $channel, $message, 'delete'), $hub->updates[0]->getData());
    }

    public function test_a_message_that_is_not_there_signals_nothing(): void
    {
        [$space, , $actor] = $this->band();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('DELETE', '/api/band_spaces/' . $space->id . '/chat/messages/3f2504e0-4f89-41d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame([], $hub->updates);
    }

    public function test_a_pin_signals(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('POST', $this->messageUrl($space, $message) . '/pin');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $hub->updates);
        $this->assertSame($this->tag($space, $channel, $message, 'pin'), $hub->updates[0]->getData());
    }

    public function test_pinning_a_message_already_pinned_signals_nothing(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author, pinnedBy: $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('POST', $this->messageUrl($space, $message) . '/pin');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $hub->updates);
    }

    public function test_a_pin_over_the_cap_signals_nothing(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        for ($index = 0; $index < 10; ++$index) {
            $this->message($channel, $author, pinnedBy: $author);
        }
        $message = $this->message($channel, $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('POST', $this->messageUrl($space, $message) . '/pin');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame([], $hub->updates);
    }

    public function test_an_unpin_signals(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author, pinnedBy: $author);
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($actor);
        $this->client->request('DELETE', $this->messageUrl($space, $message) . '/pin');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $hub->updates);
        $this->assertSame($this->tag($space, $channel, $message, 'pin'), $hub->updates[0]->getData());
    }

    public function test_a_hub_that_is_down_leaves_the_reaction_saved(): void
    {
        [$space, $channel, $actor, $author] = $this->band();
        $message = $this->message($channel, $author);
        self::getContainer()->set(RecordingHub::class, new ThrowingHub());

        $this->client->loginUser($actor);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(1, self::getContainer()->get(MessageReactionRepository::class)->count(['message' => $message]));
    }

    /**
     * The actor, the author, one who left, one kicked, and somebody active in another space.
     *
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

    private function message(MessageThread $channel, User $author, ?User $pinnedBy = null): Message
    {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'pinnedDatetime' => $pinnedBy !== null ? new \DateTimeImmutable('2026-09-11 08:00:00') : null,
            'pinnedBy' => $pinnedBy,
        ])->create();
    }

    private function tag(BandSpace $space, MessageThread $channel, Message $message, string $change): string
    {
        return json_encode([
            'type' => 'band_space_message_changed',
            'band_space_id' => (string) $space->id,
            'thread_id' => (string) $channel->id,
            'message_id' => (string) $message->id,
            'change' => $change,
        ], JSON_THROW_ON_ERROR);
    }

    private function messageUrl(BandSpace $space, Message $message): string
    {
        return '/api/band_spaces/' . $space->id . '/chat/messages/' . $message->id;
    }

    private function react(BandSpace $space, Message $message, string $emoji): void
    {
        $this->client->jsonRequest('POST', $this->messageUrl($space, $message) . '/reactions', ['emoji' => $emoji], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    private function edit(BandSpace $space, Message $message, string $content): void
    {
        $this->client->jsonRequest('PATCH', $this->messageUrl($space, $message), ['content' => $content], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }
}
