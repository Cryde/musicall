<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Mercure\MercureTopic;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Tests\Double\RecordingHub;
use App\Tests\Double\ThrowingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The two paths the API test cannot reach: a console command ending, and the hub being down.
 */
#[ResetDatabase]
class BandSpaceChangeSignalTest extends KernelTestCase
{
    /**
     * A failed exit still publishes: a command flushes as it goes, so what it wrote before failing is real.
     */
    public function test_a_command_ending_publishes_what_it_changed_even_when_it_failed(): void
    {
        self::bootKernel();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        self::getContainer()->get(BandSpaceChangeSignal::class)->changed($space, BandSpaceModule::File);
        self::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new ConsoleTerminateEvent(new Command('app:band-space:purge'), new ArrayInput([]), new NullOutput(), Command::FAILURE),
            ConsoleEvents::TERMINATE,
        );

        $updates = self::getContainer()->get(RecordingHub::class)->updates;
        $this->assertCount(1, $updates);
        $this->assertSame([MercureTopic::userNotifications((string) $member->id)], $updates[0]->getTopics());
        $this->assertSame(
            json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $space->id, 'module' => 'file']),
            $updates[0]->getData(),
        );
    }

    public function test_an_unreachable_hub_is_logged_and_does_not_throw(): void
    {
        self::bootKernel();
        $member = UserFactory::new()->create(['username' => 'member', 'email' => 'member@example.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Could not publish a band space change signal, its screens will update on their next load',
            $this->callback(static fn (array $context): bool => $context['band_space_id'] === (string) $space->id
                && $context['module'] === 'task'
                && $context['exception'] instanceof \RuntimeException),
        );
        $signal = new BandSpaceChangeSignal(new ThrowingHub(), $logger, self::getContainer()->get(BandSpaceMembershipRepository::class));

        $signal->changed($space, BandSpaceModule::Task);
        $signal->publish();
    }
}
