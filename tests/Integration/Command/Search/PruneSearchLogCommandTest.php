<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\Search;

use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\MusicianSearchKind;
use App\Repository\Search\MusicianSearchLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PruneSearchLogCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $this->commandTester = new CommandTester((new Application(self::$kernel))->find('app:search-log:prune'));
    }

    public function test_it_deletes_the_searches_older_than_six_months_by_default(): void
    {
        $this->seed(new DateTimeImmutable('-200 days'), MusicianSearchKind::Ai);
        $this->seed(new DateTimeImmutable('-190 days'), MusicianSearchKind::Filters);
        $this->seed(new DateTimeImmutable('-10 days'), MusicianSearchKind::Filters);

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Deleted 2 search(es)', $this->commandTester->getDisplay());
        $this->assertSame(1, self::getContainer()->get(MusicianSearchLogRepository::class)->count([]));
    }

    public function test_it_takes_another_number_of_days(): void
    {
        $this->seed(new DateTimeImmutable('-10 days'), MusicianSearchKind::Filters);

        $this->commandTester->execute(['--days' => '7']);

        $this->commandTester->assertCommandIsSuccessful();
        $this->assertSame(0, self::getContainer()->get(MusicianSearchLogRepository::class)->count([]));
    }

    public function test_it_rejects_non_positive_days(): void
    {
        $this->commandTester->execute(['--days' => '0']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('must be a positive number', $this->commandTester->getDisplay());
    }

    private function seed(DateTimeImmutable $when, MusicianSearchKind $kind): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new MusicianSearchLog($kind, hash('sha256', 'visitor'), $when));
        $entityManager->flush();
    }
}
