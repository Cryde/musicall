<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Enum\User\UserEmailType;
use App\Repository\UserRepository;
use App\Service\Mail\Brevo\User\ConfirmEmailReminderEmail;
use App\Service\User\UserEmailLogService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:user:send-email-confirmation-reminder',
    description: 'Send reminder emails to users who have not confirmed their email address'
)]
class SendEmailConfirmationReminderCommand extends Command
{
    private const int MAX_REMINDERS = 2;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConfirmEmailReminderEmail $confirmEmailReminderEmail,
        private readonly UserEmailLogService $userEmailLogService,
        private readonly RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since-days', 'd', InputOption::VALUE_REQUIRED, 'Send reminders to users who registered at least this many days ago', '2')
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
            '<info>Finding users who registered before %s (%d days ago) and have not confirmed their email...</info>',
            $cutoffDate->format('Y-m-d'),
            $sinceDays
        ));

        $usersWithUnconfirmedEmail = $this->userRepository->findUsersWithUnconfirmedEmail($cutoffDate);
        $output->writeln(sprintf('<info>Found %d users with unconfirmed email</info>', count($usersWithUnconfirmedEmail)));

        if (count($usersWithUnconfirmedEmail) === 0) {
            $output->writeln('<info>No users to notify</info>');

            return Command::SUCCESS;
        }

        $sentCount = 0;
        $skippedMaxRemindersCount = 0;
        $errorCount = 0;

        foreach ($usersWithUnconfirmedEmail as $user) {
            if ($limit > 0 && $sentCount >= $limit) {
                $output->writeln(sprintf('<info>Reached limit of %d emails</info>', $limit));
                break;
            }

            // Check if we already sent the maximum number of reminders
            $reminderCount = $this->userEmailLogService->countSent($user, UserEmailType::EMAIL_CONFIRMATION_REMINDER);
            if ($reminderCount >= self::MAX_REMINDERS) {
                $skippedMaxRemindersCount++;
                continue;
            }

            $confirmationLink = $this->router->generate(
                'app_register_confirm',
                ['token' => $user->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            if ($dryRun) {
                $output->writeln(sprintf(
                    '  [DRY-RUN] Would send reminder #%d to: %s (%s)',
                    $reminderCount + 1,
                    $user->getUsername(),
                    $user->getEmail()
                ));
                $sentCount++;
                continue;
            }

            try {
                $this->confirmEmailReminderEmail->send(
                    $user->getEmail(),
                    $user->getUsername(),
                    $confirmationLink
                );
                $this->userEmailLogService->log(
                    $user,
                    UserEmailType::EMAIL_CONFIRMATION_REMINDER,
                    null,
                    ['reminder_number' => $reminderCount + 1]
                );
                $sentCount++;

                $output->writeln(sprintf(
                    '  <info>Sent reminder #%d to: %s (%s)</info>',
                    $reminderCount + 1,
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
        $output->writeln(sprintf('  - Skipped (max %d reminders reached): %d', self::MAX_REMINDERS, $skippedMaxRemindersCount));

        if ($errorCount > 0) {
            $output->writeln(sprintf('  - Errors: %d', $errorCount));
        }

        return Command::SUCCESS;
    }
}
