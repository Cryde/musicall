<?php declare(strict_types=1);

namespace App\Tests\Api\Message;

use App\Entity\Message\MessageThreadMeta;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Message\MessageThreadMetaFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Brevo down while the worker sends: the email is retried with backoff, then lands in `failed`, and only
 * then is the thread's email throttle released (#1156, #1151). The real retry policy and failure
 * transport run against a mocked clock.
 */
#[ResetDatabase]
class MessageEmailWorkerFailureTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use InteractsWithMessenger;

    /** The `email` transport's waits before retries 1 to 5, in seconds: 1, 3, 9, 27 and 81 minutes. */
    private const array RETRY_WAITS = [60, 180, 540, 1620, 4860];

    public function test_a_message_email_is_retried_then_failed_and_only_its_last_failure_releases_the_throttle(): void
    {
        $clock = self::mockTime('2026-11-14 10:00:00');
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'sender', 'email' => 'sender@example.com']);
        $recipient = UserFactory::new()->asBaseUser()->create(['username' => 'recipient', 'email' => 'recipient@example.com']);
        $email = $this->transport('email')->intercept();

        $this->client->loginUser($sender);
        $this->client->jsonRequest('POST', '/api/messages/user', [
            'recipient' => '/api/users/' . $recipient->id,
            'content' => 'Salut !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertTrue($this->recipientPendingNotificationSent($recipient->id));
        $this->failEveryEmailSend();

        foreach (self::RETRY_WAITS as $retry => $waitSeconds) {
            $email->process();

            $this->assertTrue($this->recipientPendingNotificationSent($recipient->id), 'kept while retry ' . ($retry + 1) . ' is pending');
            $email->queue()->assertCount(1);
            // The strategy adds up to 10% jitter either way.
            $this->assertEqualsWithDelta($waitSeconds * 1000, $email->queue()->first()->last(DelayStamp::class)?->getDelay(), $waitSeconds * 100, 'wait before retry ' . ($retry + 1));
            $clock->sleep($waitSeconds * 1.1);
        }
        $email->process();

        $email->queue()->assertEmpty();
        $this->assertSame([SendEmailMessage::class], $this->classesSentToFailed());
        $this->assertFalse($this->recipientPendingNotificationSent($recipient->id));
    }

    public function test_another_email_failing_for_good_leaves_the_throttle_alone(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $meta = MessageThreadMetaFactory::createOne(['pendingNotificationSent' => true]);
        $email = $this->transport('email')->intercept();

        $this->client->jsonRequest('POST', '/api/users/request-reset-password', [
            'login' => 'base_user_1',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->failEveryEmailSend();
        $email->disableRetries()->process();

        $this->assertSame([SendEmailMessage::class], $this->classesSentToFailed());
        $this->assertTrue($this->pendingNotificationSentOf($meta->id));
    }

    /** Brevo refusing the call: the mailer transport fails once it is actually sending. */
    private function failEveryEmailSend(): void
    {
        self::getContainer()->get('event_dispatcher')->addListener(MessageEvent::class, static function (MessageEvent $event): void {
            if (!$event->isQueued()) {
                throw new TransportException('Brevo refused the call');
            }
        });
    }

    private function recipientPendingNotificationSent(string $recipientId): bool
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(MessageThreadMeta::class)->findOneBy(['user' => $recipientId])->pendingNotificationSent;
    }

    private function pendingNotificationSentOf(string $metaId): bool
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(MessageThreadMeta::class, $metaId)->pendingNotificationSent;
    }

    /** @return list<class-string> */
    private function classesSentToFailed(): array
    {
        $transport = self::getContainer()->get('messenger.transport.failed');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): string => $envelope->getMessage()::class, $transport->getSent()));
    }
}
