<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Repository\User\UserDailyActivityRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user-activity:prune',
    description: 'Delete the daily platform activity of members older than a given number of months',
)]
class PruneUserActivityCommand extends Command
{
    /** Thirteen months, as the privacy policy says (#1370). */
    private const int DEFAULT_MONTHS = 13;

    public function __construct(
        private readonly UserDailyActivityRepository $userDailyActivityRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('months', 'm', InputOption::VALUE_REQUIRED, 'Delete the days older than this many months', (string) self::DEFAULT_MONTHS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $months = (int) $input->getOption('months');
        if ($months <= 0) {
            $output->writeln('<error>Option --months must be a positive number</error>');

            return Command::FAILURE;
        }

        $cutoff = $this->clock->now()->modify(sprintf('-%d months', $months));
        $deleted = $this->userDailyActivityRepository->deleteBefore($cutoff);

        $output->writeln(sprintf('<info>Deleted %d daily activity row(s) before %s</info>', $deleted, $cutoff->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
