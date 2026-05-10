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

        try {
            if (!($user = $this->userRepository->find($userId)) instanceof \App\Entity\User) {
                $output->writeln(sprintf('<error>User with ID "%s" not found</error>', $userId));

                return Command::FAILURE;
            }
        } catch (\Exception $exception) {
            $output->writeln(sprintf('<error>User with ID "%s" not found</error>', $userId));

            return Command::FAILURE;
        }

        if (!($preference = $user->notificationPreference) instanceof \App\Entity\User\UserNotificationPreference) {
            $preference = new UserNotificationPreference();
            $preference->user = $user;
            $user->notificationPreference = $preference;
            $this->entityManager->persist($preference);
        }

        $preference->siteNews = false;
        $preference->weeklyRecap = false;
        $preference->messageReceived = false;
        $preference->publicationComment = false;
        $preference->forumReply = false;
        $preference->marketing = false;
        $preference->activityReminder = false;

        $this->entityManager->flush();

        $output->writeln(sprintf(
            '<info>All notifications disabled for user "%s"</info>',
            $user->username
        ));

        return Command::SUCCESS;
    }
}
