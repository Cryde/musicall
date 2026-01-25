<?php

declare(strict_types=1);

namespace App\Command\User;

use App\Entity\User\UserNotificationPreference;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:disable-notifications',
    description: 'Disable all email notifications for a user'
)]
class DisableUserNotificationsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'The user ID (UUID)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user-id');
        if (!$user = $this->userRepository->find($userId)) {
            $output->writeln(sprintf('<error>User with ID "%s" not found</error>', $userId));

            return Command::FAILURE;
        }

        if (!$preference = $user->getNotificationPreference()) {
            $preference = new UserNotificationPreference();
            $preference->setUser($user);
            $user->setNotificationPreference($preference);
            $this->entityManager->persist($preference);
        }

        $preference->setSiteNews(false);
        $preference->setWeeklyRecap(false);
        $preference->setMessageReceived(false);
        $preference->setPublicationComment(false);
        $preference->setForumReply(false);
        $preference->setMarketing(false);
        $preference->setActivityReminder(false);

        $this->entityManager->flush();

        $output->writeln(sprintf(
            '<info>All notifications disabled for user "%s"</info>',
            $user->getUsername()
        ));

        return Command::SUCCESS;
    }
}
