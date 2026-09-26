<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Identifier;

use App\Service\Identifier\DailyVisitorIdentifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class DailyVisitorIdentifierTest extends TestCase
{
    public function test_the_same_visitor_is_the_same_all_day_and_someone_else_the_next(): void
    {
        $identifier = new DailyVisitorIdentifier('secret');
        $visitor = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $other = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.8']);
        $morning = new \DateTimeImmutable('2026-09-27 08:00:00');
        $evening = new \DateTimeImmutable('2026-09-27 22:00:00');
        $nextDay = new \DateTimeImmutable('2026-09-28 08:00:00');

        $this->assertSame($identifier->fromRequest($visitor, $morning), $identifier->fromRequest($visitor, $evening));
        $this->assertNotSame($identifier->fromRequest($visitor, $morning), $identifier->fromRequest($visitor, $nextDay));
        $this->assertNotSame($identifier->fromRequest($visitor, $morning), $identifier->fromRequest($other, $morning));
        $this->assertStringNotContainsString('203.0.113.7', $identifier->fromRequest($visitor, $morning));
    }

    public function test_the_secret_is_part_of_the_hash(): void
    {
        $visitor = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $day = new \DateTimeImmutable('2026-09-27');

        $this->assertNotSame(
            (new DailyVisitorIdentifier('one'))->fromRequest($visitor, $day),
            (new DailyVisitorIdentifier('two'))->fromRequest($visitor, $day),
        );
    }
}
