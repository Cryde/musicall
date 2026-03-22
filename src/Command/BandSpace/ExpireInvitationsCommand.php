<?php declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Repository\BandSpace\BandSpaceInvitationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:band-space:expire-invitations',
    description: 'Mark expired band space invitations as expired'
)]
class ExpireInvitationsCommand extends Command
{
    public function __construct(
        private readonly BandSpaceInvitationRepository $invitationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->invitationRepository->markExpired();

        $io->success(sprintf('%d invitation(s) marquée(s) comme expirée(s).', $count));

        return Command::SUCCESS;
    }
}
