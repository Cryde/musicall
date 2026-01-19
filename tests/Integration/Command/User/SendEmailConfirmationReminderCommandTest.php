<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Command\User\SendEmailConfirmationReminderCommand;
use App\Entity\User;
use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use App\Service\Mail\Brevo\User\ConfirmEmailReminderEmail;
use App\Tests\Factory\User\UserEmailLogFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SendEmailConfirmationReminderCommandTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        // Stub the email service to avoid actually sending emails
        $emailStub = $this->createStub(ConfirmEmailReminderEmail::class);
        self::getContainer()->set(ConfirmEmailReminderEmail::class, $emailStub);

        $this->commandTester = new CommandTester(self::getContainer()->get(SendEmailConfirmationReminderCommand::class));
    }

    public function test_command_with_no_unconfirmed_users(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 users with unconfirmed email', $output);
        $this->assertStringContainsString('No users to notify', $output);
    }

    public function test_command_finds_unconfirmed_users(): void
    {
        $this->createUnconfirmedUser(5);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 users with unconfirmed email', $output);
        $this->assertStringContainsString('[DRY-RUN] Would send reminder #1 to:', $output);
    }

    public function test_command_respects_since_days_option(): void
    {
        // User registered 3 days ago (should not be found with default 2 days, but should be with 5 days)
        $this->createUnconfirmedUser(3);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 users with unconfirmed email', $output);

        // With 5 days threshold, user registered 3 days ago should NOT be found
        $this->commandTester->execute(['--dry-run' => true, '--since-days' => '5']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 users with unconfirmed email', $output);
    }

    public function test_command_respects_limit_option(): void
    {
        $this->createUnconfirmedUser(5);
        $this->createUnconfirmedUser(6);
        $this->createUnconfirmedUser(7);

        $this->commandTester->execute(['--dry-run' => true, '--limit' => '2']);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Reached limit of 2 emails', $output);
        $this->assertStringContainsString('Emails to send: 2', $output);
    }

    public function test_command_skips_users_with_max_reminders_reached(): void
    {
        $user = $this->createUnconfirmedUser(5);

        // Simulate 2 reminders already sent
        UserEmailLogFactory::new()->emailConfirmationReminder(1)->create(['user' => $user]);
        UserEmailLogFactory::new()->emailConfirmationReminder(2)->create(['user' => $user]);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Emails to send: 0', $output);
        $this->assertStringContainsString('Skipped (max 2 reminders reached): 1', $output);
    }

    public function test_command_sends_second_reminder_after_first(): void
    {
        $user = $this->createUnconfirmedUser(5);

        // Simulate 1 reminder already sent
        UserEmailLogFactory::new()->emailConfirmationReminder(1)->create(['user' => $user]);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[DRY-RUN] Would send reminder #2 to:', $output);
        $this->assertStringContainsString('Emails to send: 1', $output);
    }

    public function test_command_logs_sent_emails(): void
    {
        $this->createUnconfirmedUser(5);

        $repository = self::getContainer()->get(UserEmailLogRepository::class);
        $this->assertSame(0, $repository->count());

        $this->commandTester->execute([]);

        $this->assertSame(1, $repository->count());

        // Verify the log has correct email type
        $logs = $repository->findAll();
        $this->assertSame(UserEmailType::EMAIL_CONFIRMATION_REMINDER, $logs[0]->getEmailType());
        $this->assertSame(['reminder_number' => 1], $logs[0]->getMetadata());
    }

    public function test_command_logs_correct_reminder_number_for_second_reminder(): void
    {
        $user = $this->createUnconfirmedUser(5);
        UserEmailLogFactory::new()
            ->emailConfirmationReminder(1)
            ->create([
                'user' => $user,
                'sentDatetime' => new \DateTimeImmutable('-1 day'),
            ]);

        $this->commandTester->execute([]);

        $repository = self::getContainer()->get(UserEmailLogRepository::class);
        $logs = $repository->findBy(['user' => $user], ['sentDatetime' => 'DESC']);

        $this->assertCount(2, $logs);
        // The most recent log should be reminder #2
        $this->assertSame(['reminder_number' => 2], $logs[0]->getMetadata());
    }

    public function test_dry_run_does_not_log_emails(): void
    {
        $this->createUnconfirmedUser(5);

        $repository = self::getContainer()->get(UserEmailLogRepository::class);

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $repository->count());
    }

    public function test_command_skips_users_without_token(): void
    {
        // Create user with no token (shouldn't happen in practice, but test the safety)
        UserFactory::new()->create([
            'confirmationDatetime' => null,
            'token' => null,
            'creationDatetime' => new \DateTime('-5 days'),
        ]);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 users with unconfirmed email', $output);
    }

    public function test_command_skips_already_confirmed_users(): void
    {
        // Create confirmed user
        UserFactory::new()->create([
            'confirmationDatetime' => new \DateTime('-3 days'),
            'token' => 'some-token',
            'creationDatetime' => new \DateTime('-5 days'),
        ]);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 0 users with unconfirmed email', $output);
    }

    public function test_command_fails_with_invalid_since_days(): void
    {
        $this->commandTester->execute(['--since-days' => '0']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Option --since-days must be a positive number', $output);
    }

    public function test_command_fails_with_negative_since_days(): void
    {
        $this->commandTester->execute(['--since-days' => '-5']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Option --since-days must be a positive number', $output);
    }

    private function createUnconfirmedUser(int $daysAgo): User
    {
        /** @var User $user */
        $user = UserFactory::new()->create([
            'confirmationDatetime' => null,
            'token' => bin2hex(random_bytes(16)),
            'creationDatetime' => new \DateTime(sprintf('-%d days', $daysAgo)),
        ])->_real();

        return $user;
    }
}
