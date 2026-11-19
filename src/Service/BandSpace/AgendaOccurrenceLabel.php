<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use DateTimeImmutable;
use IntlDateFormatter;

/** One date of an agenda entry written out for a reader, as an email has no client to format it. */
final class AgendaOccurrenceLabel
{
    /** Read in the entry's own timezone, as the agenda does, so an all day date never moves a day. */
    public static function format(DateTimeImmutable $occurrenceStart, bool $isAllDay, string $locale): string
    {
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            $isAllDay ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT,
            AgendaAggregator::timezoneFor($isAllDay),
        );

        return (string) $formatter->format($occurrenceStart);
    }
}
