<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Command\User\SendInactivityReminderCommand;
use App\Entity\User\UserNotificationPreference;
use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use App\Service\Mail\Brevo\User\InactivityReminderEmail;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SendInactivityReminderCommandTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        // Stub the email service to avoid actually sending emails
        $emailStub = $this->createStub(InactivityReminderEmail::class);
        self::getContainer()->set(InactivityReminderEmail::class, $emailStub);

        $application = new Application(self::$kernel);
        $command = $application->find('app:user:send-inactivity-reminder');
        $this->commandTester = new CommandTester($command);
    }

    public function test_command_with_no_inactive_users(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 inactive users', $output);
        $this->assertStringContainsString('No inactive users to notify', $output);
    }

    public function test_command_finds_inactive_users(): void
    {
        $this->createInactiveUser(90);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 inactive users', $output);
        $this->assertStringContainsString('[DRY-RUN] Would send to:', $output);
    }

    public function test_command_respects_since_days_option(): void
    {
        // User inactive for 30 days (should not be found with default 60 days)
        $this->createInactiveUser(30);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 inactive users', $output);

        // Now with 20 days threshold, should find the user
        $this->commandTester->execute(['--dry-run' => true, '--since-days' => '20']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 inactive users', $output);
    }

    public function test_command_respects_limit_option(): void
    {
        $this->createInactiveUser(90);
        $this->createInactiveUser(100);
        $this->createInactiveUser(110);

        $this->commandTester->execute(['--dry-run' => true, '--limit' => '2']);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Reached limit of 2 emails', $output);
        $this->assertStringContainsString('Emails to send: 2', $output);
    }

    public function test_command_skips_already_notified_users(): void
    {
        $user = $this->createInactiveUser(90);

        // First run - should send
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Emails sent: 1', $output);
        $this->assertStringContainsString('Skipped (already sent): 0', $output);

        // Second run - should skip
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Emails sent: 0', $output);
        $this->assertStringContainsString('Skipped (already sent): 1', $output);
    }

    public function test_command_logs_sent_emails(): void
    {
        $this->createInactiveUser(90);

        $repository = self::getContainer()->get(UserEmailLogRepository::class);
        $this->assertSame(0, $repository->count());

        $this->commandTester->execute([]);

        $this->assertSame(1, $repository->count());
    }

    public function test_dry_run_does_not_log_emails(): void
    {
        $this->createInactiveUser(90);

        $repository = self::getContainer()->get(UserEmailLogRepository::class);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $repository->count());
    }

    public function test_command_only_finds_confirmed_users(): void
    {
        // Create unconfirmed user (inactive)
        UserFactory::new()->create([
            'confirmationDatetime' => null,
            'lastLoginDatetime' => new \DateTime('-90 days'),
        ]);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 inactive users', $output);
    }

    public function test_command_only_finds_users_who_have_logged_in(): void
    {
        // Create user who never logged in
        UserFactory::new()->create([
            'confirmationDatetime' => new \DateTime('-90 days'),
            'lastLoginDatetime' => null,
        ]);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 inactive users', $output);
    }

    public function test_command_skips_users_who_disabled_activity_reminder(): void
    {
        $user = $this->createInactiveUser(90);

        // Create notification preference with activity reminder disabled
        $preference = new UserNotificationPreference();
        $preference->setUser($user);
        $preference->setActivityReminder(false);
        $user->setNotificationPreference($preference);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($preference);
        $em->flush();

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 inactive users', $output);
        $this->assertStringContainsString('Emails to send: 0', $output);
        $this->assertStringContainsString('Skipped (user preferences): 1', $output);
    }

    public function test_command_fails_with_invalid_since_days(): void
    {
        $this->commandTester->execute(['--since-days' => '0']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Option --since-days must be a positive number', $output);
    }

    private function createInactiveUser(int $daysAgo): \App\Entity\User
    {
        return UserFactory::new()->create([
            'confirmationDatetime' => new \DateTime('-1 year'),
            'lastLoginDatetime' => new \DateTime(sprintf('-%d days', $daysAgo)),
        ])->_real();
    }
}
