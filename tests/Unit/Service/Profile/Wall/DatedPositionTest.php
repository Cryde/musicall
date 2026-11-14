<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Profile\Wall;

use App\Service\Profile\Wall\DatedPosition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The columns hold the server's local time, and Doctrine writes a bound date as its wall clock, so the cursor's
 * instant must be read in the server's zone. CI runs in UTC, where a missed conversion changes nothing.
 */
class DatedPositionTest extends TestCase
{
    private string $serverZone;

    protected function setUp(): void
    {
        $this->serverZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverZone);
    }

    public function test_the_bound_date_is_the_server_s_wall_clock(): void
    {
        // 10:00 UTC is 11:00 in Paris, as a comment written then is stored.
        $position = DatedPosition::of(new \DateTimeImmutable('2026-11-02T10:00:00+00:00'), '01a1b220-df08-7910-9e2a-d78c147e10d0');
        $query = new QueryBuilder($this->createStub(EntityManagerInterface::class));

        $condition = $position->afterNewestFirst($query, 'c.creationDatetime', 'c.id');

        $this->assertSame('c.creationDatetime < :positionDate OR (c.creationDatetime = :positionDate AND c.id < :positionId)', $condition);
        $date = $query->getParameter('positionDate')?->getValue();
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2026-11-02 11:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('01a1b220-df08-7910-9e2a-d78c147e10d0', $query->getParameter('positionId')?->getValue());
    }

    public function test_a_position_parsed_from_a_cursor_binds_the_same_wall_clock(): void
    {
        $position = DatedPosition::parse('1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0');
        $this->assertInstanceOf(DatedPosition::class, $position);
        $query = new QueryBuilder($this->createStub(EntityManagerInterface::class));

        $position->upToOldestFirst($query, 'c.creationDatetime', 'c.id');

        $date = $query->getParameter('positionDate')?->getValue();
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2026-11-02 11:00:00', $date->format('Y-m-d H:i:s'));
    }
}
