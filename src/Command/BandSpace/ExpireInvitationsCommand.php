<?php declare(strict_types=1);

namespace App\Command\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\InvitationStatus;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly BandSpaceInvitationRepository $invitationRepository,
        private readonly BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expired = $this->invitationRepository->findExpiredPending();

        foreach ($expired as $invitation) {
            $invitation->status = InvitationStatus::Expired;

            $this->bandSpaceActivityRecorder->record(
                bandSpace: $invitation->bandSpace,
                module: BandSpaceModule::Settings,
                type: BandSpaceSettingsActivityType::InvitationExpired,
                resourceId: $invitation->id,
                actor: null,
                payload: ['email' => $invitation->email],
            );
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d invitation(s) marquée(s) comme expirée(s).', count($expired)));

        return Command::SUCCESS;
    }
}
