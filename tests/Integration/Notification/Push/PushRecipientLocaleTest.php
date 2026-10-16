<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Enum\Notification\NotificationType;
use App\Event\MessagePostedEvent;
use App\EventSubscriber\MessagePostedListener;
use App\Messenger\SendPushNotification;
use App\Service\BandSpace\BandSpaceMemberNames;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\BandSpace\ChatMentionResolver;
use App\Service\Message\MessagePlainTextExtractor;
use App\Service\Message\ThreadMemberResolver;
use App\Service\Notification\NotificationCreator;
use App\Service\Notification\Push\PushContentBuilder;
use App\Service\Notification\Push\PushQueue;
use App\Service\User\UserNotificationPreferenceChecker;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\RecordingTranslator;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A push speaks its recipient's language, not the actor's (#1191): it is read once per locale among them. */
#[ResetDatabase]
class PushRecipientLocaleTest extends KernelTestCase
{
    public function test_a_notification_is_translated_in_each_recipients_locale(): void
    {
        $french = UserFactory::new()->asBaseUser()->create(['username' => 'first_member', 'email' => 'first@example.com']);
        $english = UserFactory::new()->asBaseUser()->create(['username' => 'second_member', 'email' => 'second@example.com', 'locale' => 'en']);
        $alsoFrench = UserFactory::new()->asBaseUser()->create(['username' => 'third_member', 'email' => 'third@example.com']);
        $translator = new RecordingTranslator(self::getContainer()->get(TranslatorInterface::class));

        $creator = new NotificationCreator(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(RecordingHub::class),
            new NullLogger(),
            $this->builder($translator),
            self::getContainer()->get(PushQueue::class),
        );
        $creator->createForRecipients([$french, $english, $alsoFrench], NotificationType::TaskComment, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'task_id' => 't1',
            'task_title' => 'Répéter le pont',
            'actor_username' => 'alice',
        ]);

        $this->assertSame([
            ['id' => 'notification.task_comment', 'locale' => 'fr'],
            ['id' => 'notification.task_comment', 'locale' => 'en'],
        ], $translator->calls);
        // Queued locale by locale. Only French exists yet, so every recipient still reads the French sentence.
        $this->assertSame(
            [
                [(string) $french->id, 'alice a commenté la tâche « Répéter le pont »'],
                [(string) $alsoFrench->id, 'alice a commenté la tâche « Répéter le pont »'],
                [(string) $english->id, 'alice a commenté la tâche « Répéter le pont »'],
            ],
            array_map(static fn (SendPushNotification $push): array => [$push->userId, $push->body], $this->queuedPushes()),
        );
    }

    public function test_a_band_message_is_translated_in_the_recipients_locale_not_the_authors(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@example.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob@example.com', 'locale' => 'en']);
        $band = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        foreach ([$author, $member] as $user) {
            BandSpaceMembershipFactory::new(['bandSpace' => $band, 'user' => $user])->create();
        }
        $channel = MessageThreadFactory::new()->forBandSpace($band)->create();
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author, 'content' => 'On répète mardi ?'])->create();
        $translator = new RecordingTranslator(self::getContainer()->get(TranslatorInterface::class));

        $listener = new MessagePostedListener(
            self::getContainer()->get(RecordingHub::class),
            new NullLogger(),
            self::getContainer()->get(ThreadMemberResolver::class),
            self::getContainer()->get(ChatMentionResolver::class),
            self::getContainer()->get(BandSpaceMemberNames::class),
            $this->builder($translator),
            self::getContainer()->get(PushQueue::class),
            self::getContainer()->get(UserNotificationPreferenceChecker::class),
        );
        $listener(new MessagePostedEvent($message));

        $this->assertSame([['id' => 'message.band_channel_body', 'locale' => 'en']], $translator->calls);
        $this->assertSame(
            [[(string) $member->id, 'alice_drums : On répète mardi ?']],
            array_map(static fn (SendPushNotification $push): array => [$push->userId, $push->body], $this->queuedPushes()),
        );
    }

    public function test_the_default_title_and_an_attachment_are_translated_in_the_given_locale(): void
    {
        $attachment = MessageFactory::new(['thread' => MessageThreadFactory::new()->create(), 'content' => ''])->create();
        $translator = new RecordingTranslator(self::getContainer()->get(TranslatorInterface::class));
        $builder = $this->builder($translator);

        $this->assertSame('MusicAll', $builder->forNotification(NotificationType::ForumTopicReply, ['actor_username' => 'bob'], 'en')->title);
        $this->assertSame('Pièce jointe', $builder->forMessage($attachment, 'Alice', 'en')->body);
        $this->assertSame([
            ['id' => 'notification.forum_topic_reply', 'locale' => 'en'],
            ['id' => 'title.default', 'locale' => 'en'],
            ['id' => 'message.attachment_only', 'locale' => 'en'],
        ], $translator->calls);
    }

    private function builder(TranslatorInterface $translator): PushContentBuilder
    {
        return new PushContentBuilder(
            self::getContainer()->get(MessagePlainTextExtractor::class),
            self::getContainer()->get(ChatMentionRenderer::class),
            $translator,
        );
    }

    /** @return list<SendPushNotification> */
    private function queuedPushes(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }
}
