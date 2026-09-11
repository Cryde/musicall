<?php

declare(strict_types=1);

namespace App\Tests\Integration\Message;

use App\Event\MessageSentEvent;
use App\Mercure\MercureTopic;
use App\Repository\Message\MessageRepository;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
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

    private function threadWith(\App\Entity\User ...$participants): \App\Entity\Message\MessageThread
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
