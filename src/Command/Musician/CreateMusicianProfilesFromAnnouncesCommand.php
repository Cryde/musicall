<?php

declare(strict_types=1);

namespace App\Command\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileInstrument;
use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Repository\Musician\MusicianAnnounceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:musician:create-profiles-from-announces',
    description: 'Create musician profiles for users who have musician announces (type band) but no profile yet'
)]
class CreateMusicianProfilesFromAnnouncesCommand extends Command
{
    public function __construct(
        private readonly MusicianAnnounceRepository $musicianAnnounceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Running in dry-run mode - no changes will be persisted');
        }

        $announces = $this->musicianAnnounceRepository->findBy(['type' => MusicianAnnounce::TYPE_BAND]);

        $io->info(sprintf('Found %d musician announces (type band)', count($announces)));

        // Group announces by user
        $announcesByUser = [];
        foreach ($announces as $announce) {
            $userId = $announce->getAuthor()->getId();
            if (!isset($announcesByUser[$userId])) {
                $announcesByUser[$userId] = [];
            }
            $announcesByUser[$userId][] = $announce;
        }

        $io->info(sprintf('Found %d unique users with announces', count($announcesByUser)));

        $created = 0;
        $skipped = 0;

        foreach ($announcesByUser as $userAnnounces) {
            /** @var MusicianAnnounce $firstAnnounce */
            $firstAnnounce = $userAnnounces[0];
            $user = $firstAnnounce->getAuthor();

            // Skip if user already has a musician profile
            if ($user->getMusicianProfile() !== null) {
                $skipped++;
                $io->text(sprintf('  Skipped: %s (already has profile)', $user->getUsername()));
                continue;
            }

            // Create musician profile
            $profile = new MusicianProfile();
            $profile->setUser($user);
            $profile->setAvailabilityStatus(AvailabilityStatus::LOOKING_FOR_BAND);

            // Collect all unique instruments and styles from all announces
            $instrumentIds = [];
            $instrumentNames = [];

            foreach ($userAnnounces as $announce) {
                $instrument = $announce->getInstrument();
                $instrumentId = $instrument->getId();

                // Add instrument only if not already added (avoid duplicates)
                if (!isset($instrumentIds[$instrumentId])) {
                    $instrumentIds[$instrumentId] = true;
                    $instrumentNames[] = $instrument->getMusicianName();

                    $profileInstrument = new MusicianProfileInstrument();
                    $profileInstrument->setInstrument($instrument);
                    $profileInstrument->setSkillLevel(SkillLevel::INTERMEDIATE);
                    $profile->addInstrument($profileInstrument);
                }

                // Add styles (MusicianProfile::addStyle already handles duplicates)
                foreach ($announce->getStyles() as $style) {
                    $profile->addStyle($style);
                }
            }

            $user->setMusicianProfile($profile);

            if (!$dryRun) {
                $this->entityManager->persist($profile);
            }

            $created++;
            $io->text(sprintf('  Created: %s (instruments: %s, styles: %d)',
                $user->getUsername(),
                implode(', ', $instrumentNames),
                $profile->getStyles()->count()
            ));
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            'Done! Created: %d profiles, Skipped: %d (already had profile)',
            $created,
            $skipped
        ));

        if ($dryRun && $created > 0) {
            $io->warning('This was a dry run. Run without --dry-run to persist changes.');
        }

        return Command::SUCCESS;
    }
}
