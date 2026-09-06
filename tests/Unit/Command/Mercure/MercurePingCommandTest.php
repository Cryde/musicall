<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Mercure;

use App\Command\Mercure\MercurePingCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Double\RecordingHub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePingCommandTest extends TestCase
{
    public function test_it_publishes_privately_on_the_named_users_topic(): void
    {
        $user = new User();
        $user->id = 'd1be73fc-b0c4-4530-a30a-d41f43e6ebea';
        $user->username = 'user_base';

        $hub = new RecordingHub();
        $tester = new CommandTester($this->command($hub, $user));

        $this->assertSame(Command::SUCCESS, $tester->execute(['username' => 'user_base']));

        $update = $hub->published;
        self::assertInstanceOf(Update::class, $update);
        $this->assertSame(['/users/d1be73fc-b0c4-4530-a30a-d41f43e6ebea/notifications'], $update->getTopics());
        // The flag is the isolation, not the token: the hub only checks a subscriber's selectors for
        // private updates, so a public one here would reach every signed-in browser that asked.
        $this->assertTrue($update->isPrivate());
        $this->assertStringContainsString('/users/d1be73fc-b0c4-4530-a30a-d41f43e6ebea/notifications', $tester->getDisplay());
    }

    public function test_an_unknown_username_publishes_nothing(): void
    {
        $hub = new RecordingHub();
        $tester = new CommandTester($this->command($hub, null));

        $this->assertSame(Command::FAILURE, $tester->execute(['username' => 'nobody']));
        $this->assertNull($hub->published);
        $this->assertStringContainsString('No user named "nobody"', $tester->getDisplay());
    }

    private function command(HubInterface $hub, ?User $user): MercurePingCommand
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        return new MercurePingCommand($hub, $userRepository);
    }
}
