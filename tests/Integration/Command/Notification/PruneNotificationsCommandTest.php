<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\Notification;

use App\Repository\Notification\NotificationRepository;
use App\Tests\Factory\Notification\NotificationFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PruneNotificationsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:notification:prune');
        $this->commandTester = new CommandTester($command);
    }

    public function test_it_deletes_only_read_notifications_older_than_cutoff(): void
    {
        $user = UserFactory::new()->create();

        // Deleted: read 40 days ago (older than the default 30-day cutoff).
        NotificationFactory::new(['recipient' => $user])->read(new DateTimeImmutable('-40 days'))->create();
        // Kept: read recently.
        NotificationFactory::new(['recipient' => $user])->read(new DateTimeImmutable('-5 days'))->create();
        // Kept: still unread, regardless of age.
        NotificationFactory::new([
            'recipient' => $user,
            'creationDatetime' => new DateTimeImmutable('-90 days'),
        ])->create();

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Deleted 1 read notification(s)', $this->commandTester->getDisplay());

        $repository = self::getContainer()->get(NotificationRepository::class);
        $this->assertSame(2, $repository->count([]));
    }

    public function test_it_rejects_non_positive_days(): void
    {
        $this->commandTester->execute(['--days' => '0']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('must be a positive number', $this->commandTester->getDisplay());
    }
}
