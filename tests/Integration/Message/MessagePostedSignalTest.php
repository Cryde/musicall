<?php

declare(strict_types=1);

namespace App\Tests\Integration\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Event\MessageSentEvent;
use App\Mercure\MercureTopic;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageRepository;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class MessagePostedSignalTest extends KernelTestCase
{
    public function test_sending_signals_every_participant_including_the_sender(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $first = UserFactory::new()->asBaseUser()->create(['username' => 'first', 'email' => 'first@test.com']);
        $second = UserFactory::new()->asBaseUser()->create(['username' => 'second', 'email' => 'second@test.com']);
        $thread = $this->threadWith($sender, $first, $second);

        self::getContainer()->get(MessageSenderProcedure::class)->processByThread($thread, $sender, 'hello');

        // The sender too: a send from the laptop has to update the same person's phone.
        self::assertSame(
            [
                MercureTopic::userNotifications((string) $sender->id),
                MercureTopic::userNotifications((string) $first->id),
                MercureTopic::userNotifications((string) $second->id),
            ],
            $hub->publishedTopics()
        );

        $update = $hub->updates[0];
        // Without this the hub stops consulting subscriber topic selectors and hands the signal to
        // every connected browser, whatever their token says.
        self::assertTrue($update->isPrivate());
        // A tag, not the message: no content travels over the hub, so nothing here renders it.
        self::assertSame(
            '{"type":"message","thread_id":"' . $thread->id . '"}',
            $update->getData()
        );
    }

    public function test_the_signal_does_not_depend_on_the_email_throttle(): void
    {
        // MessageSentEvent is dispatched only when shouldNotify() agrees, and one of its rules skips
        // anybody active in the last five minutes. That is exactly the person looking at the thread,
        // so reusing it would have starved the live update of the case it exists for.
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $recipient = UserFactory::new()->asBaseUser()->create([
            'username' => 'recipient',
            'email' => 'recipient@test.com',
            'lastActivityDatetime' => new \DateTimeImmutable(),
        ]);
        $thread = $this->threadWith($sender, $recipient);

        self::getContainer()->get(MessageSenderProcedure::class)->processByThread($thread, $sender, 'hello');

        self::assertCount(1, $hub->updates);
        self::assertContains(
            MercureTopic::userNotifications((string) $recipient->id),
            $hub->publishedTopics()
        );
    }

    public function test_starting_a_conversation_signals_the_recipient_too(): void
    {
        // The other entry point, and the one that matters most for the inbox: the thread is brand new,
        // so the recipient has no row to update and has to pick up a whole new one.
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@test.com']);

        self::getContainer()->get(MessageSenderProcedure::class)->process($sender, $recipient, 'hello');

        self::assertContains(
            MercureTopic::userNotifications((string) $recipient->id),
            $hub->publishedTopics()
        );
    }

    public function test_an_unreachable_hub_still_leaves_the_message_sent(): void
    {
        // The message is the thing that matters. Losing the live update costs a refresh; losing the
        // message would lose it for good, and a hub is a side channel that must never fail a send.
        self::bootKernel();
        $container = self::getContainer();
        $container->set(RecordingHub::class, new ThrowingHub());

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@test.com']);
        $thread = $this->threadWith($sender, $recipient);

        $message = $container->get(MessageSenderProcedure::class)->processByThread($thread, $sender, 'hello');

        self::assertNotNull(
            $container->get(MessageRepository::class)->find($message->id),
            'A hub that is down must not take the message with it'
        );
    }

    public function test_an_email_that_fails_does_not_take_the_live_signal_with_it(): void
    {
        // Found in dev against a provider that was genuinely refusing us: with the emails dispatched
        // first, one failing send meant a 500 and not a single subscriber heard anything. The signal
        // is local and swallows its own failures, so it goes first.
        self::bootKernel();
        $container = self::getContainer();
        $hub = $container->get(RecordingHub::class);
        $container->get(EventDispatcherInterface::class)->addListener(
            MessageSentEvent::class,
            static fn () => throw new \RuntimeException('the email provider is down'),
        );

        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@test.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@test.com']);
        $thread = $this->threadWith($sender, $recipient);

        try {
            $container->get(MessageSenderProcedure::class)->processByThread($thread, $sender, 'hello');
            self::fail('the email listener was supposed to throw');
        } catch (\RuntimeException) {
            // The email failure still surfaces, which is pre-existing behaviour and not what this pins.
        }

        self::assertContains(
            MercureTopic::userNotifications((string) $recipient->id),
            $hub->publishedTopics(),
            'The live signal must be published before anything that can fail'
        );
    }

    public function test_a_channel_message_signals_every_active_member_and_nobody_else(): void
    {
        // A channel has no MessageParticipant rows at all: its members are derived from the space
        // (#959). Before #963 that made this listener iterate an empty collection and publish nothing,
        // so a band's conversation was the one that never went live.
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        [$space, $channel, $sender, $others, $kicked, $stranger] = $this->band();

        self::getContainer()->get(MessageSenderProcedure::class)->processByThread($channel, $sender, 'on répète mardi');

        // The two absences by name first, so a leak is reported as "the kicked member" rather than as
        // one uuid missing from a diff of three.
        self::assertNotContains(MercureTopic::userNotifications((string) $kicked->id), $hub->publishedTopics());
        self::assertNotContains(MercureTopic::userNotifications((string) $stranger->id), $hub->publishedTopics());
        // Then the exact list, which is what catches somebody nobody thought to name.
        self::assertSame(
            [
                MercureTopic::userNotifications((string) $sender->id),
                MercureTopic::userNotifications((string) $others[0]->id),
                MercureTopic::userNotifications((string) $others[1]->id),
            ],
            $hub->publishedTopics()
        );

        $update = $hub->updates[0];
        // Without this the hub stops consulting subscriber topic selectors and hands the signal to
        // every connected browser, whatever their token says.
        self::assertTrue($update->isPrivate());
        // Its own type, because the inbox does not list channels and must not refetch for one, and
        // keyed by the space because that is what the chat API takes.
        self::assertSame(
            '{"type":"band_space_message","band_space_id":"' . $space->id . '"}',
            $update->getData()
        );
    }

    public function test_a_member_who_leaves_stops_receiving_at_once(): void
    {
        // The sharp edge of #963, and the reason the topic is per user rather than per space. The
        // recipient list is computed from BandSpaceMembership at publish time, so leaving takes effect
        // on the very next message. A shared channel topic would instead have kept delivering until
        // the subscriber token was reissued, and "within the token lifetime" would then have been a
        // stated security property rather than none at all.
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        [, $channel, $sender, $others] = $this->band();
        $leaver = $others[0];
        $procedure = self::getContainer()->get(MessageSenderProcedure::class);

        $procedure->processByThread($channel, $sender, 'on répète mardi');
        self::assertContains(MercureTopic::userNotifications((string) $leaver->id), $hub->publishedTopics());

        $membership = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneBy(['bandSpace' => $channel->bandSpace, 'user' => $leaver->id]);
        self::assertNotNull($membership);
        $membership->status = MembershipStatus::Left;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $hub->updates = [];
        $procedure->processByThread($channel, $sender, 'finalement jeudi');

        self::assertNotContains(MercureTopic::userNotifications((string) $leaver->id), $hub->publishedTopics());
        self::assertContains(MercureTopic::userNotifications((string) $others[1]->id), $hub->publishedTopics());
    }

    /**
     * A band of three active members, one kicked, and one person with no membership at all.
     *
     * @return array{0: BandSpace, 1: MessageThread, 2: User, 3: User[], 4: User, 5: User}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);

        // Pinned creation datetimes: findByBandSpace() orders by them, and the exact topic list above
        // would otherwise flip on a faker tie.
        $sender = $this->member($space, 'batteur', '2026-01-01 10:00:00');
        $others = [
            $this->member($space, 'bassiste', '2026-01-01 11:00:00'),
            $this->member($space, 'chanteuse', '2026-01-01 12:00:00'),
        ];
        $kicked = $this->member($space, 'ancien', '2026-01-01 13:00:00', MembershipStatus::Kicked);
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);

        return [
            $space,
            MessageThreadFactory::new()->forBandSpace($space)->create(),
            $sender,
            $others,
            $kicked,
            $stranger,
        ];
    }

    private function member(
        BandSpace $bandSpace,
        string $username,
        string $joinedAt,
        MembershipStatus $status = MembershipStatus::Active,
    ): User {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => $username,
            'email' => $username . '@test.com',
        ]);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => $status,
            // Mutable, because that is how BandSpaceMembership maps the column.
            'creationDatetime' => new \DateTime($joinedAt),
        ])->create();

        return $user;
    }

    private function threadWith(User ...$participants): MessageThread
    {
        $thread = MessageThreadFactory::new()->create();
        foreach ($participants as $participant) {
            MessageParticipantFactory::new(['thread' => $thread, 'participant' => $participant])->create();
            MessageThreadMetaFactory::new([
                'thread' => $thread,
                'user' => $participant,
                'lastReadDatetime' => null,
            ])->create();
        }

        return $thread;
    }
}

final class ThrowingHub implements \Symfony\Component\Mercure\HubInterface
{
    public function publish(\Symfony\Component\Mercure\Update $update): string
    {
        throw new \RuntimeException('the hub is down');
    }

    public function getPublicUrl(): string
    {
        return '/.well-known/mercure';
    }

    public function getFactory(): ?\Symfony\Component\Mercure\Jwt\TokenFactoryInterface
    {
        return null;
    }

    public function getProtocolVersion(): \Symfony\Component\Mercure\ProtocolVersion
    {
        return \Symfony\Component\Mercure\ProtocolVersion::Legacy;
    }

    public function getCookieName(): string
    {
        return 'mercureAuthorization';
    }
}
