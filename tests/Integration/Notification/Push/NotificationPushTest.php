<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Enum\Notification\NotificationType;
use App\Enum\Notification\PushCategory;
use App\Messenger\SendPushNotification;
use App\Service\Notification\NotificationCreator;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Everything the bell shows also goes to the recipients' phones, queued rather than sent (#1110). */
#[ResetDatabase]
class NotificationPushTest extends KernelTestCase
{
    public function test_a_notification_queues_one_push_per_recipient_behind_its_category(): void
    {
        $first = UserFactory::new()->asBaseUser()->create(['username' => 'first_member', 'email' => 'first@example.com']);
        $second = UserFactory::new()->asBaseUser()->create(['username' => 'second_member', 'email' => 'second@example.com']);

        self::getContainer()->get(NotificationCreator::class)->createForRecipients([$first, $second, $first], NotificationType::TaskComment, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'task_id' => 't1',
            'task_title' => 'Répéter le pont',
            'actor_username' => 'alice',
        ]);

        $pushes = $this->queuedPushes();
        $this->assertSame([(string) $first->id, (string) $second->id], array_map(static fn (SendPushNotification $push): string => $push->userId, $pushes));
        $this->assertSame(PushCategory::BandTasks, $pushes[0]->category);
        $this->assertSame('Les Cactus', $pushes[0]->title);
        $this->assertSame('alice a commenté la tâche « Répéter le pont »', $pushes[0]->body);
        $this->assertSame(['type' => 'task_comment', 'route' => '/band/b1/tasks/t1'], $pushes[0]->data);
    }

    public function test_a_report_stays_in_the_bell(): void
    {
        $reporter = UserFactory::new()->asBaseUser()->create();

        self::getContainer()->get(NotificationCreator::class)->create($reporter, NotificationType::ReportReceived, ['target_type' => 'user', 'target_label' => 'bob']);

        $this->assertSame([], $this->queuedPushes());
    }

    /** @return list<SendPushNotification> */
    private function queuedPushes(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return array_values(array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));
    }
}
