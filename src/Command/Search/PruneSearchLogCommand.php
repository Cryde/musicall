<?php

declare(strict_types=1);

namespace App\Command\Search;

use App\Repository\Search\MusicianSearchLogRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:search-log:prune',
    description: 'Delete the recorded musician searches older than a given number of days',
)]
class PruneSearchLogCommand extends Command
{
    /** Six months, as the privacy policy says (#1075). */
    private const int DEFAULT_DAYS = 183;

    public function __construct(private readonly MusicianSearchLogRepository $musicianSearchLogRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete the searches older than this many days', (string) self::DEFAULT_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days <= 0) {
            $output->writeln('<error>Option --days must be a positive number</error>');

            return Command::FAILURE;
        }

        $cutoff = new DateTimeImmutable(sprintf('-%d days', $days));
        $deleted = $this->musicianSearchLogRepository->deleteOlderThan($cutoff);

        $output->writeln(sprintf('<info>Deleted %d search(es) made before %s</info>', $deleted, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
