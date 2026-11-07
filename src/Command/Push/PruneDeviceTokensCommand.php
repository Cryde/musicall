<?php

declare(strict_types=1);

namespace App\Command\Push;

use App\Repository\User\DeviceTokenRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:device-token:prune',
    description: 'Delete the push device tokens not seen for a given number of days',
)]
class PruneDeviceTokensCommand extends Command
{
    /**
     * FCM's own expiry for an unused token. FCM calls a token stale after a month but still delivers to it,
     * and a push may be what brings a member back, so only what FCM would no longer deliver to goes.
     */
    private const int DEFAULT_DAYS = 270;

    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete the tokens not seen for this many days', (string) self::DEFAULT_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days <= 0) {
            $output->writeln('<error>Option --days must be a positive number</error>');

            return Command::FAILURE;
        }

        $cutoff = $this->clock->now()->modify(sprintf('-%d days', $days));
        $deleted = $this->deviceTokenRepository->deleteLastSeenBefore($cutoff);

        $output->writeln(sprintf('<info>Deleted %d device token(s) last seen before %s</info>', $deleted, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
