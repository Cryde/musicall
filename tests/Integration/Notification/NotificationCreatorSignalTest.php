<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification;

use App\Enum\Notification\NotificationType;
use App\Mercure\MercureTopic;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class NotificationCreatorSignalTest extends KernelTestCase
{
    public function test_creating_a_notification_signals_that_recipient_privately(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);
        $creator = self::getContainer()->get(NotificationCreator::class);

        $recipient = UserFactory::new()->asBaseUser()->create();
        $creator->create($recipient, NotificationType::ForumTopicReply, ['topic_slug' => 'a-topic']);

        self::assertCount(1, $hub->updates);
        $update = $hub->updates[0];
        self::assertSame([MercureTopic::userNotifications($recipient->id)], $update->getTopics());

        // Without this the hub stops consulting subscriber topic selectors and hands the signal to
        // every connected browser, whatever their token says.
        self::assertTrue($update->isPrivate());

        // A tag, not the notification: the browser refetches through the API it already uses.
        self::assertSame('{"type":"notification"}', $update->getData());
    }

    public function test_a_recipient_named_twice_is_signalled_once(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);
        $creator = self::getContainer()->get(NotificationCreator::class);

        $first = UserFactory::new()->asBaseUser()->create();
        $second = UserFactory::new()->create(['username' => 'second', 'email' => 'second@test.com']);

        // The same de-duplication the persist side already does, and a null, which is skipped.
        $creator->createForRecipients(
            [$first, $second, $first, null],
            NotificationType::ForumTopicReply,
            ['topic_slug' => 'a-topic']
        );

        self::assertSame(
            [MercureTopic::userNotifications($first->id), MercureTopic::userNotifications($second->id)],
            $hub->publishedTopics()
        );
    }

    public function test_no_recipient_means_no_signal(): void
    {
        self::bootKernel();
        $hub = self::getContainer()->get(RecordingHub::class);

        self::getContainer()->get(NotificationCreator::class)
            ->createForRecipients([null], NotificationType::ForumTopicReply, []);

        self::assertSame([], $hub->updates);
    }

    public function test_an_unreachable_hub_still_leaves_the_notification_created(): void
    {
        self::bootKernel();

        // Built by hand rather than through the container, because the point is the one dependency
        // the container deliberately never provides: a hub that fails.
        $creator = new NotificationCreator(
            self::getContainer()->get(EntityManagerInterface::class),
            new ThrowingHub(),
            new NullLogger(),
        );

        $recipient = UserFactory::new()->asBaseUser()->create();
        $creator->create($recipient, NotificationType::ForumTopicReply, ['topic_slug' => 'a-topic']);

        // The notification is the thing that matters. Losing the live update costs a minute of
        // latency; losing the notification would lose it for good.
        self::assertCount(
            1,
            self::getContainer()->get(NotificationRepository::class)->findBy(['recipient' => $recipient])
        );
    }
}

final class ThrowingHub implements HubInterface
{
    public function publish(Update $update): string
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
