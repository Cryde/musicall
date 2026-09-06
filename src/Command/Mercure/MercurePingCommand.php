<?php

declare(strict_types=1);

namespace App\Command\Mercure;

use App\Mercure\MercureTopic;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes one update on a user's notification topic, and nothing else.
 *
 * A smoke test rather than a feature. The hub is a Caddy directive, and in production that Caddyfile
 * lives on the server rather than in this repository, alongside environment variables that Deployer
 * shares rather than deploys: a release can therefore go out with a perfectly green test suite and a
 * hub that nothing can reach. Running this after a deploy, with the browser open on the site, is what
 * says the whole chain works.
 */
#[AsCommand(
    name: 'app:mercure:ping',
    description: 'Publish a test update on a user\'s Mercure notification topic, to check the hub is reachable',
)]
class MercurePingCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username whose notification topic to publish on');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if ($user === null) {
            $output->writeln(sprintf('<error>No user named "%s"</error>', $username));

            return Command::FAILURE;
        }

        $topic = MercureTopic::userNotifications($user->id);

        // Private, and that is the whole point: the hub only checks a subscriber's topic selectors
        // for private updates, so a public one on this topic would reach anybody holding any valid
        // subscriber token who thought to ask for it.
        $this->hub->publish(new Update(
            $topic,
            (string) json_encode(['type' => 'ping', 'sent_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)]),
            private: true,
        ));

        $output->writeln(sprintf('Published on <info>%s</info>', $topic));

        return Command::SUCCESS;
    }
}
