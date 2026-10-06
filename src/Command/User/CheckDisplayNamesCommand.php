<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\UserRepository;
use App\Service\User\DisplayNameRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Names chosen before the display name rules existed were never checked. This lists the profile and
 * stage names that break them, and clears them with --fix so the username shows instead.
 */
#[AsCommand(
    name: 'app:user:check-display-names',
    description: 'List profile and stage names that break the display name rules, and clear them with --fix',
)]
class CheckDisplayNamesCommand extends Command
{
    private const string USERNAME_TAKEN = 'username_taken';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BandSpaceMembershipRepository $membershipRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Clear every name that breaks a rule');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = (bool) $input->getOption('fix');
        $rows = [];

        foreach ($this->userRepository->findWithProfileName() as $user) {
            $reason = $this->reasonToRefuse((string) $user->profile->displayName, $user);
            if ($reason !== null) {
                $rows[] = ['profile', $user->username, $user->profile->displayName, $reason];
                if ($fix) {
                    $user->profile->displayName = null;
                }
            }
        }

        foreach ($this->membershipRepository->findWithStageName() as $membership) {
            $reason = $this->reasonToRefuse((string) $membership->stageName, $membership->user);
            if ($reason !== null) {
                $rows[] = ['stage name', $membership->user->username, $membership->stageName, $reason];
                if ($fix) {
                    $membership->stageName = null;
                }
            }
        }

        if ($rows === []) {
            $io->success('Every display name follows the rules.');

            return Command::SUCCESS;
        }

        $io->table(['Kind', 'Username', 'Name', 'Rule'], $rows);

        if ($fix) {
            $this->entityManager->flush();
            $io->success(sprintf('%d name(s) cleared.', count($rows)));
        } else {
            $io->warning(sprintf('%d name(s) break a rule. Run again with --fix to clear them.', count($rows)));

            // Failure, so a scheduled run can alert on it.
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /** Same rules as the DisplayName constraint, with the owner taken from the row rather than the session. */
    private function reasonToRefuse(string $name, User $owner): ?string
    {
        $normalized = DisplayNameRules::normalize($name);
        if ($normalized === null) {
            return null;
        }

        $violation = DisplayNameRules::violation($normalized);
        if ($violation !== null) {
            return $violation;
        }

        return $this->userRepository->isUsernameOfAnotherUser($normalized, $owner) ? self::USERNAME_TAKEN : null;
    }
}
