<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Enum\User\UserEmailType;
use App\Repository\UserRepository;
use App\Service\Mail\Brevo\User\InactivityReminderEmail;
use App\Service\User\UserEmailLogService;
use App\Service\User\UserNotificationPreferenceChecker;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:send-inactivity-reminder',
    description: 'Send reminder emails to users who have been inactive for a specified number of days'
)]
class SendInactivityReminderCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly InactivityReminderEmail $inactivityReminderEmail,
        private readonly UserEmailLogService $userEmailLogService,
        private readonly UserNotificationPreferenceChecker $notificationPreferenceChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since-days', 'd', InputOption::VALUE_REQUIRED, 'Number of days of inactivity', '60')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview which users would receive emails without sending')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of emails to send (0 for no limit)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sinceDays = (int) $input->getOption('since-days');
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        if ($sinceDays <= 0) {
            $output->writeln('<error>Option --since-days must be a positive number</error>');

            return Command::FAILURE;
        }

        $cutoffDate = new DateTimeImmutable(sprintf('-%d days', $sinceDays));
        $output->writeln(sprintf(
            '<info>Finding users inactive since %s (%d days ago)...</info>',
            $cutoffDate->format('Y-m-d'),
            $sinceDays
        ));

        $inactiveUsers = $this->userRepository->findInactiveUsersSince($cutoffDate);
        $output->writeln(sprintf('<info>Found %d inactive users</info>', count($inactiveUsers)));

        if (count($inactiveUsers) === 0) {
            $output->writeln('<info>No inactive users to notify</info>');

            return Command::SUCCESS;
        }

        $sentCount = 0;
        $skippedAlreadySentCount = 0;
        $skippedPreferencesCount = 0;
        $errorCount = 0;

        foreach ($inactiveUsers as $user) {
            if ($limit > 0 && $sentCount >= $limit) {
                $output->writeln(sprintf('<info>Reached limit of %d emails</info>', $limit));
                break;
            }

            // Check if user accepted to receive activity reminder emails
            if (!$this->notificationPreferenceChecker->canReceiveActivityReminderNotification($user)) {
                $skippedPreferencesCount++;
                continue;
            }

            // Check if we already sent an inactivity reminder to this user
            if ($this->userEmailLogService->hasBeenSent($user, UserEmailType::INACTIVITY_REMINDER)) {
                $skippedAlreadySentCount++;
                continue;
            }

            $lastLoginDate = $user->getLastLoginDatetime()?->format('d/m/Y') ?? 'N/A';

            if ($dryRun) {
                $output->writeln(sprintf(
                    '  [DRY-RUN] Would send to: %s (%s) - last login: %s',
                    $user->getUsername(),
                    $user->getEmail(),
                    $lastLoginDate
                ));
                $sentCount++;
                continue;
            }

            try {
                $this->inactivityReminderEmail->send(
                    $user->getEmail(),
                    $user->getUsername(),
                    $lastLoginDate
                );
                $this->userEmailLogService->log($user, UserEmailType::INACTIVITY_REMINDER);
                $sentCount++;

                $output->writeln(sprintf(
                    '  <info>Sent to: %s (%s)</info>',
                    $user->getUsername(),
                    $user->getEmail()
                ));
            } catch (\Throwable $e) {
                $errorCount++;
                $output->writeln(sprintf(
                    '  <error>Failed to send to %s: %s</error>',
                    $user->getEmail(),
                    $e->getMessage()
                ));
            }
        }

        $output->writeln('');
        $output->writeln('<info>Summary:</info>');
        $output->writeln(sprintf('  - Emails %s: %d', $dryRun ? 'to send' : 'sent', $sentCount));
        $output->writeln(sprintf('  - Skipped (already sent): %d', $skippedAlreadySentCount));
        $output->writeln(sprintf('  - Skipped (user preferences): %d', $skippedPreferencesCount));

        if ($errorCount > 0) {
            $output->writeln(sprintf('  - Errors: %d', $errorCount));
        }

        return Command::SUCCESS;
    }
}
