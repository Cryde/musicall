<?php

declare(strict_types=1);

namespace App\Command\Notification;

use App\Repository\Notification\NotificationRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notification:prune',
    description: 'Delete read notifications older than a given number of days to keep the table bounded',
)]
class PruneNotificationsCommand extends Command
{
    private const int DEFAULT_DAYS = 30;

    public function __construct(private readonly NotificationRepository $notificationRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete read notifications older than this many days', (string) self::DEFAULT_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days <= 0) {
            $output->writeln('<error>Option --days must be a positive number</error>');

            return Command::FAILURE;
        }

        $cutoff = new DateTimeImmutable(sprintf('-%d days', $days));
        $deleted = $this->notificationRepository->deleteReadOlderThan($cutoff);

        $output->writeln(sprintf(
            '<info>Deleted %d read notification(s) read before %s</info>',
            $deleted,
            $cutoff->format('Y-m-d H:i:s'),
        ));

        return Command::SUCCESS;
    }
}
