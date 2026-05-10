<?php declare(strict_types=1);

namespace App\Tests\Integration\Repository\BandSpace;

use App\Repository\BandSpace\AgendaEntryRepository;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class AgendaEntryRepositoryTest extends KernelTestCase
{
    public function test_find_upcoming_for_band_returns_entries_within_window_only(): void
    {
        $repo = static::getContainer()->get(AgendaEntryRepository::class);

        $band = BandSpaceFactory::createOne();
        $otherBand = BandSpaceFactory::createOne();

        $inWindow = AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 10:00:00'),
        ]);
        AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-05-15 10:00:00'),
        ]);
        AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-08-15 10:00:00'),
        ]);
        AgendaEntryFactory::createOne([
            'bandSpace' => $otherBand,
            'eventDatetime' => new DateTimeImmutable('2026-06-15 10:00:00'),
        ]);

        $results = $repo->findUpcomingForBand(
            $band,
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-30 23:59:59'),
        );

        $this->assertCount(1, $results);
        $this->assertSame($inWindow, $results[0]);
    }

    public function test_find_upcoming_for_band_orders_by_event_datetime_asc(): void
    {
        $repo = static::getContainer()->get(AgendaEntryRepository::class);

        $band = BandSpaceFactory::createOne();

        $later = AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-06-20 10:00:00'),
        ]);
        $earlier = AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-06-05 10:00:00'),
        ]);
        $middle = AgendaEntryFactory::createOne([
            'bandSpace' => $band,
            'eventDatetime' => new DateTimeImmutable('2026-06-12 10:00:00'),
        ]);

        $results = $repo->findUpcomingForBand(
            $band,
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-30 23:59:59'),
        );

        $this->assertCount(3, $results);
        $this->assertSame($earlier, $results[0]);
        $this->assertSame($middle, $results[1]);
        $this->assertSame($later, $results[2]);
    }
}
