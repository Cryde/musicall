<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\AgendaItem;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Serialises agenda items into the RFC 5545 document a calendar client subscribes to.
 *
 * Occurrences are emitted expanded rather than as an RRULE. AgendaAggregator already expands them,
 * in civil time and with cancellations applied (#821), and our recurrence model is not a full RRULE,
 * so deriving one here would be a second source of truth free to disagree with the app.
 *
 * Hand rolled rather than pulled from a library: eight properties are emitted, and every library
 * brings its own event model, so the mapping layer would be most of the code anyway. The risk lives
 * in the folding and escaping rules, and that is what the unit test covers.
 */
final class IcalFeedBuilder
{
    /** RFC 5545 3.1: no content line exceeds 75 octets, and every break is a CRLF. */
    private const int FOLD_OCTETS = 75;

    private const string LINE_BREAK = "\r\n";

    /** The right hand side of a UID, so ours cannot collide with another publisher's. */
    private const string UID_DOMAIN = '@musicall.com';

    private const string PRODUCT_ID = '-//MusicAll//Band Space Agenda//FR';

    /** What honest clients are asked to wait between polls. Outlook reads X-PUBLISHED-TTL. */
    private const string REFRESH_INTERVAL = 'PT12H';

    /**
     * @param AgendaItem[] $items      already narrowed to the sources the feed publishes
     * @param DateTimeImmutable $generatedAt what every DTSTAMP carries. Passed in rather than read
     *                                       off the clock so the body is byte identical between two
     *                                       polls that find nothing changed, which is the whole
     *                                       basis of the ETag on this endpoint.
     */
    public function build(array $items, string $calendarName, DateTimeImmutable $generatedAt): string
    {
        $stamp = $this->utcStamp($generatedAt);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODUCT_ID,
            'CALSCALE:GREGORIAN',
            // Deliberately no METHOD. METHOD:PUBLISH makes Outlook treat the document as a one off
            // import rather than as a subscription it keeps refreshing.
            'X-WR-CALNAME:' . $this->escapeText($calendarName),
            'X-PUBLISHED-TTL:' . self::REFRESH_INTERVAL,
            'REFRESH-INTERVAL;VALUE=DURATION:' . self::REFRESH_INTERVAL,
        ];

        foreach ($items as $item) {
            array_push($lines, ...$this->buildEvent($item, $stamp));
        }

        $lines[] = 'END:VCALENDAR';

        return implode(self::LINE_BREAK, array_map($this->fold(...), $lines)) . self::LINE_BREAK;
    }

    /**
     * @return string[]
     */
    private function buildEvent(AgendaItem $item, string $stamp): array
    {
        $lines = [
            // AgendaItem::$id is already stable across fetches, occurrences included, which is the
            // property that matters: a UID that moved would have the subscriber's calendar filling
            // up with a fresh copy of every event on every poll.
            'UID:' . $item->id . self::UID_DOMAIN,
            'DTSTAMP:' . $stamp,
        ];

        array_push($lines, ...($item->isAllDay ? $this->allDayBounds($item) : $this->timedBounds($item)));

        $lines[] = 'SUMMARY:' . $this->escapeText($item->title);

        if ($item->description !== null && $item->description !== '') {
            $lines[] = 'DESCRIPTION:' . $this->escapeText($item->description);
        }

        $location = $item->metadata['location'] ?? null;
        if (is_string($location) && $location !== '') {
            $lines[] = 'LOCATION:' . $this->escapeText($location);
        }

        return ['BEGIN:VEVENT', ...$lines, 'END:VEVENT'];
    }

    /**
     * @return string[]
     */
    private function allDayBounds(AgendaItem $item): array
    {
        $firstDay = $this->writtenDay($item->datetime);
        // Our end is the last day the entry covers; RFC 5545 wants the day after it. That is the
        // same conversion assets/js/utils/agendaDate.js does for FullCalendar, which reads an all
        // day end as exclusive too. An entry with no end covers its single day.
        $lastDay = $item->endDatetime !== null ? $this->writtenDay($item->endDatetime) : $firstDay;

        return [
            'DTSTART;VALUE=DATE:' . $firstDay->format('Ymd'),
            'DTEND;VALUE=DATE:' . $lastDay->modify('+1 day')->format('Ymd'),
        ];
    }

    /**
     * @return string[]
     */
    private function timedBounds(AgendaItem $item): array
    {
        $lines = ['DTSTART:' . $this->utcStamp(new DateTimeImmutable($item->datetime))];

        // No DTEND when the entry has none. RFC 5545 reads a DATE-TIME start with no end as an event
        // ending at its start, which is exactly what the app knows; inventing a duration would put a
        // length on the subscriber's calendar that nobody typed.
        if ($item->endDatetime !== null) {
            $lines[] = 'DTEND:' . $this->utcStamp(new DateTimeImmutable($item->endDatetime));
        }

        return $lines;
    }

    /**
     * The day an all day item was written for, read off the front of the string rather than parsed
     * and converted.
     *
     * All day items reach here pinned to midnight UTC by the aggregator. Converting that pin instead
     * of reading it is what #877 and #912 were about: west of UTC the day moves back one, and the
     * event lands in the wrong cell of the subscriber's calendar rather than merely showing an odd
     * hour.
     */
    private function writtenDay(string $atom): DateTimeImmutable
    {
        return new DateTimeImmutable(substr($atom, 0, 10) . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * A UTC instant, with the trailing Z that saves the document from carrying a VTIMEZONE block.
     */
    private function utcStamp(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * RFC 5545 3.3.11.
     *
     * The backslash pass runs first, so a backslash the user typed is doubled before this method
     * starts adding its own and doubling those too.
     *
     * Control characters are dropped rather than escaped, because TEXT has no representation for
     * them. Removing them by byte is safe on the accented titles this app is full of: none of the
     * removed values can appear inside a UTF-8 sequence, whose bytes are all >= 0x80. It also
     * disposes of the null byte, which this project has met before (#934).
     */
    private function escapeText(string $value): string
    {
        $withoutControls = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $withoutControls,
        );
    }

    /**
     * RFC 5545 3.1.
     *
     * The 75 counts octets, and the leading space of a continuation counts toward its own 75. The
     * part worth being careful about is that a fold must never land inside a UTF-8 sequence:
     * splitting « Répétition » mid character is the bug this app would actually hit, and it produces
     * a calendar the client refuses rather than a cosmetic glitch.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::FOLD_OCTETS) {
            return $line;
        }

        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            // Not valid UTF-8, so there is no safe place to cut. An over long line is a lesser
            // failure than a truncated one.
            return $line;
        }

        $folded = [];
        $current = '';

        foreach ($characters as $character) {
            if (strlen($current) + strlen($character) > self::FOLD_OCTETS) {
                $folded[] = $current;
                $current = ' ';
            }
            $current .= $character;
        }

        $folded[] = $current;

        return implode(self::LINE_BREAK, $folded);
    }
}
