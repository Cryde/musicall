<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\AgendaOccurrenceLabel;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** The date an availability reminder email names (#1418). */
class AgendaOccurrenceLabelTest extends TestCase
{
    public function test_a_timed_date_is_shown_in_the_band_civil_time_in_winter(): void
    {
        $start = new DateTimeImmutable('2030-01-15 20:00', new DateTimeZone('UTC'));

        self::assertSame('mardi 15 janvier 2030 à 21:00', AgendaOccurrenceLabel::format($start, false, 'fr'));
    }

    public function test_a_timed_date_follows_summer_time_and_the_reader_locale(): void
    {
        $start = new DateTimeImmutable('2030-07-15 20:00', new DateTimeZone('UTC'));

        self::assertSame("Monday, July 15, 2030 at 10:00\u{202F}PM", AgendaOccurrenceLabel::format($start, false, 'en'));
    }

    public function test_a_date_just_after_midnight_in_paris_is_named_by_its_paris_day(): void
    {
        // Its UTC day is the 14th, but the band meets on the 15th.
        $start = new DateTimeImmutable('2030-01-15 00:30', new DateTimeZone('Europe/Paris'));

        self::assertSame('mardi 15 janvier 2030 à 00:30', AgendaOccurrenceLabel::format($start, false, 'fr'));
    }

    public function test_an_all_day_date_is_the_written_day_without_a_time(): void
    {
        $start = new DateTimeImmutable('2030-03-31 00:00', new DateTimeZone('UTC'));

        self::assertSame('dimanche 31 mars 2030', AgendaOccurrenceLabel::format($start, true, 'fr'));
    }
}
