<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Entity\User\UserDailyActivity;
use App\Repository\User\UserDailyActivityRepository;
use App\Tests\Factory\User\UserDailyActivityFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PruneUserActivityCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    // 13 months before it is 2025-10-11, the default cutoff.
    private const string NOW = '2026-11-11 04:00:00';

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $this->commandTester = new CommandTester((new Application(self::$kernel))->find('app:user-activity:prune'));
    }

    public function test_it_deletes_the_days_older_than_13_months_and_keeps_the_cutoff_day(): void
    {
        self::mockTime(self::NOW);
        $this->seedDays('2025-06-20', '2025-10-10', '2025-10-11', '2026-11-10');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame("Deleted 2 daily activity row(s) before 2025-10-11\n", $this->commandTester->getDisplay());
        $this->assertSame(['2025-10-11', '2026-11-10'], $this->remainingDays());
    }

    public function test_it_takes_another_number_of_months(): void
    {
        self::mockTime(self::NOW);
        $this->seedDays('2026-08-10', '2026-08-11');

        $this->commandTester->execute(['--months' => '3']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame("Deleted 1 daily activity row(s) before 2026-08-11\n", $this->commandTester->getDisplay());
        $this->assertSame(['2026-08-11'], $this->remainingDays());
    }

    public function test_it_rejects_non_positive_months(): void
    {
        self::mockTime(self::NOW);
        $this->seedDays('2025-06-20');

        $this->commandTester->execute(['--months' => '0']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertSame("Option --months must be a positive number\n", $this->commandTester->getDisplay());
        $this->assertSame(['2025-06-20'], $this->remainingDays());
    }

    private function seedDays(string ...$days): void
    {
        $user = UserFactory::createOne();
        foreach ($days as $day) {
            UserDailyActivityFactory::createOne(['user' => $user, 'day' => new DateTimeImmutable($day)]);
        }
    }

    /** @return list<string> */
    private function remainingDays(): array
    {
        $days = array_map(
            static fn(UserDailyActivity $row): string => $row->day->format('Y-m-d'),
            self::getContainer()->get(UserDailyActivityRepository::class)->findAll(),
        );
        sort($days);

        return $days;
    }
}
