<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail\Brevo;

use App\Service\Mail\Brevo\BrevoDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrevoDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function dates(): iterable
    {
        yield 'French' => ['2026-04-03 10:00:00 UTC', 'fr', '03/04/2026'];
        yield 'English spells the month out' => ['2026-04-03 10:00:00 UTC', 'en', '3 April 2026'];
        yield 'a locale without its own format reads the French one' => ['2026-04-03 10:00:00 UTC', 'de', '03/04/2026'];
        yield 'past midnight in Paris is already the next day' => ['2026-04-02 22:30:00 UTC', 'fr', '03/04/2026'];
    }

    #[DataProvider('dates')]
    public function test_a_day_reads_on_the_civil_clock_in_the_recipient_locale(string $instant, string $locale, string $expected): void
    {
        self::assertSame($expected, BrevoDate::format(new \DateTimeImmutable($instant), $locale));
    }
}
