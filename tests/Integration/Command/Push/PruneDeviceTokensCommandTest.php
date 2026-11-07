<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\Push;

use App\Entity\User\DeviceToken;
use App\Repository\User\DeviceTokenRepository;
use App\Tests\Factory\User\DeviceTokenFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PruneDeviceTokensCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    // 270 days before it is 2026-02-11 04:00:00, the default cutoff.
    private const string NOW = '2026-11-08 04:00:00';

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $this->commandTester = new CommandTester((new Application(self::$kernel))->find('app:device-token:prune'));
    }

    public function test_it_deletes_the_tokens_not_seen_for_270_days_and_keeps_the_recent_ones(): void
    {
        self::mockTime(self::NOW);
        $this->seedToken('wiped-phone', '2025-06-20 18:30:00');
        $this->seedToken('lost-phone', '2026-01-15 09:00:00');
        $this->seedToken('daily-phone', '2026-11-07 21:00:00');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame("Deleted 2 device token(s) last seen before 2026-02-11 04:00:00\n", $this->commandTester->getDisplay());
        $this->assertSame(['daily-phone'], $this->remainingTokens());
    }

    public function test_a_token_last_seen_exactly_270_days_ago_is_kept_and_one_a_second_earlier_is_deleted(): void
    {
        self::mockTime(self::NOW);
        $this->seedToken('at-the-cutoff', '2026-02-11 04:00:00');
        $this->seedToken('one-second-past-the-cutoff', '2026-02-11 03:59:59');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame("Deleted 1 device token(s) last seen before 2026-02-11 04:00:00\n", $this->commandTester->getDisplay());
        $this->assertSame(['at-the-cutoff'], $this->remainingTokens());
    }

    public function test_it_takes_another_number_of_days(): void
    {
        self::mockTime(self::NOW);
        $this->seedToken('nine-days-old', '2026-10-30 04:00:00');
        $this->seedToken('five-days-old', '2026-11-03 04:00:00');

        $this->commandTester->execute(['--days' => '7']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame("Deleted 1 device token(s) last seen before 2026-11-01 04:00:00\n", $this->commandTester->getDisplay());
        $this->assertSame(['five-days-old'], $this->remainingTokens());
    }

    public function test_it_rejects_non_positive_days(): void
    {
        self::mockTime(self::NOW);
        $this->seedToken('wiped-phone', '2025-06-20 18:30:00');

        $this->commandTester->execute(['--days' => '0']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertSame("Option --days must be a positive number\n", $this->commandTester->getDisplay());
        $this->assertSame(['wiped-phone'], $this->remainingTokens());
    }

    private function seedToken(string $token, string $lastSeen): void
    {
        DeviceTokenFactory::new()->create(['token' => $token, 'lastSeenDatetime' => new DateTimeImmutable($lastSeen)]);
    }

    /** @return list<string> */
    private function remainingTokens(): array
    {
        $tokens = array_map(
            static fn (DeviceToken $deviceToken): string => $deviceToken->token,
            self::getContainer()->get(DeviceTokenRepository::class)->findAll(),
        );
        sort($tokens);

        return $tokens;
    }
}
