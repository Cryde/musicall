<?php

declare(strict_types=1);

namespace App\Command\Push;

use App\Messenger\SendPushNotification;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A smoke test for the whole push chain after a deploy: it goes through the queue rather than
 * straight to FCM, so it also proves the worker is running and the credentials are readable.
 */
#[AsCommand(
    name: 'app:push:test',
    description: 'Queue a test push notification to every device of a user',
)]
class SendTestPushCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'The username whose devices receive the push')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Notification title', 'MusicAll')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'Notification body', 'Notification de test')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'App path opened on tap, e.g. /band/{id}/agenda');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if ($user === null || $user->isDeleted()) {
            $output->writeln(sprintf('<error>No user named "%s"</error>', $username));

            return Command::FAILURE;
        }

        $route = $input->getOption('route');
        $this->messageBus->dispatch(new SendPushNotification(
            (string) $user->id,
            (string) $input->getOption('title'),
            (string) $input->getOption('body'),
            $route !== null ? ['route' => (string) $route] : [],
        ));

        $output->writeln(sprintf('Queued a push for <info>%s</info>; the messenger worker sends it', $username));

        return Command::SUCCESS;
    }
}
