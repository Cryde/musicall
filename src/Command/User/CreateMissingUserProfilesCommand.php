<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User;
use App\Entity\User\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create-missing-profiles',
    description: 'Create UserProfile for existing users who do not have one'
)]
class CreateMissingUserProfilesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usersWithoutProfile = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.id IS NULL')
            ->getQuery()
            ->getResult();

        $count = count($usersWithoutProfile);

        if ($count === 0) {
            $io->success('All users already have a profile.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d users without a profile.', $count));
        $io->progressStart($count);

        $batchSize = 100;
        $i = 0;

        /** @var User $user */
        foreach ($usersWithoutProfile as $user) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $profile->setCreationDatetime($user->getCreationDatetime()
                ? \DateTimeImmutable::createFromMutable($user->getCreationDatetime())
                : new \DateTimeImmutable()
            );

            $this->entityManager->persist($profile);
            $user->setProfile($profile);

            $i++;
            $io->progressAdvance();

            if (($i % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();

        $io->progressFinish();
        $io->success(sprintf('Created %d user profiles.', $count));

        return Command::SUCCESS;
    }
}
