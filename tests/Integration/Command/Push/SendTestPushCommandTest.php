<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\Push;

use App\Messenger\SendPushNotification;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SendTestPushCommandTest extends KernelTestCase
{
    public function test_queues_a_push_on_the_async_transport(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'jeanne_accordion', 'email' => 'jeanne.accordion@example.com']);

        $tester = $this->tester();
        $tester->execute(['username' => 'jeanne_accordion', '--route' => '/band/12/tasks/34']);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Queued a push for jeanne_accordion', $tester->getDisplay());

        $sent = $this->asyncTransport()->getSent();
        $this->assertCount(1, $sent);
        $this->assertEquals(
            new SendPushNotification((string) $user->id, 'MusicAll', 'Notification de test', ['route' => '/band/12/tasks/34']),
            $sent[0]->getMessage(),
        );
    }

    public function test_unknown_user(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute(['username' => 'nobody_here']));
        $this->assertStringContainsString('No user named "nobody_here"', $tester->getDisplay());
        $this->assertSame([], $this->asyncTransport()->getSent());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('app:push:test'));
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }
}
