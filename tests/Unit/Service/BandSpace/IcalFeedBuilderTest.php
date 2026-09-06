<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\ApiResource\BandSpace\AgendaItem;
use App\Service\BandSpace\IcalFeedBuilder;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class IcalFeedBuilderTest extends TestCase
{
    private IcalFeedBuilder $builder;

    private DateTimeImmutable $generatedAt;

    protected function setUp(): void
    {
        $this->builder = new IcalFeedBuilder();
        $this->generatedAt = new DateTimeImmutable('2026-09-06 00:00:00', new DateTimeZone('UTC'));
    }

    public function test_empty_feed_is_a_valid_calendar(): void
    {
        $feed = $this->builder->build([], 'Les Copains', $this->generatedAt);

        $this->assertSame(
            "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//MusicAll//Band Space Agenda//FR\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "X-WR-CALNAME:Les Copains\r\n"
            . "X-PUBLISHED-TTL:PT12H\r\n"
            . "REFRESH-INTERVAL;VALUE=DURATION:PT12H\r\n"
            . "END:VCALENDAR\r\n",
            $feed,
        );
    }

    public function test_method_is_never_emitted(): void
    {
        // METHOD:PUBLISH makes Outlook treat the document as a one off import instead of a
        // subscription it keeps refreshing.
        $feed = $this->builder->build([$this->timedItem()], 'Les Copains', $this->generatedAt);

        $this->assertStringNotContainsString('METHOD:', $feed);
    }

    public function test_a_timed_event_is_serialised_in_utc(): void
    {
        $item = $this->timedItem();
        $item->datetime = '2026-08-01T22:00:00+02:00';
        $item->endDatetime = '2026-08-02T01:30:00+02:00';

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("DTSTART:20260801T200000Z\r\n", $feed);
        $this->assertStringContainsString("DTEND:20260801T233000Z\r\n", $feed);
        $this->assertStringContainsString("DTSTAMP:20260906T000000Z\r\n", $feed);
    }

    public function test_a_timed_event_without_an_end_emits_no_dtend(): void
    {
        $item = $this->timedItem();
        $item->endDatetime = null;

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringNotContainsString('DTEND', $feed);
    }

    public function test_an_all_day_event_uses_dates_and_an_exclusive_end(): void
    {
        // Two days covered, 1 and 2 August, so RFC 5545 wants the 3rd. Getting this wrong is the
        // classic off by one that shows a festival ending the day before it does.
        $item = $this->allDayItem();
        $item->datetime = '2026-08-01T00:00:00+00:00';
        $item->endDatetime = '2026-08-02T00:00:00+00:00';

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20260801\r\n", $feed);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20260803\r\n", $feed);
        // The timed forms, which would put the entry at 02:00 on the subscriber's calendar.
        $this->assertStringNotContainsString('DTSTART:', $feed);
        $this->assertStringNotContainsString('DTEND:', $feed);
    }

    public function test_a_single_all_day_event_still_covers_one_day(): void
    {
        $item = $this->allDayItem();
        $item->datetime = '2026-08-01T00:00:00+00:00';
        $item->endDatetime = null;

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20260801\r\n", $feed);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20260802\r\n", $feed);
    }

    public function test_an_all_day_date_is_read_as_written_and_never_converted(): void
    {
        // The pin is midnight UTC by convention. A builder that parsed and converted instead of
        // reading would move the day west of UTC, which is what #877 and #912 were about.
        $item = $this->allDayItem();
        $item->datetime = '2026-08-01T00:00:00+00:00';

        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);
        } finally {
            date_default_timezone_set($previous);
        }

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20260801\r\n", $feed);
    }

    public function test_uid_is_stable_and_carries_our_domain(): void
    {
        $item = $this->timedItem();
        $item->id = 'manual-3f1c-20260801-2300';

        $first = $this->builder->build([$item], 'Les Copains', $this->generatedAt);
        $second = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("UID:manual-3f1c-20260801-2300@musicall.com\r\n", $first);
        $this->assertSame($first, $second);
    }

    public function test_text_values_are_escaped(): void
    {
        $item = $this->timedItem();
        $item->title = 'Concert; back\\slash, comma';
        $item->description = "Ligne 1\nLigne 2";

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("SUMMARY:Concert\\; back\\\\slash\\, comma\r\n", $feed);
        $this->assertStringContainsString("DESCRIPTION:Ligne 1\\nLigne 2\r\n", $feed);
    }

    public function test_control_characters_including_a_null_byte_are_dropped(): void
    {
        $item = $this->timedItem();
        $item->title = "Concert\0 au \x07Bataclan";

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("SUMMARY:Concert au Bataclan\r\n", $feed);
    }

    public function test_location_comes_from_the_metadata(): void
    {
        $item = $this->timedItem();
        $item->metadata = ['location' => 'Le Botanique, Bruxelles'];

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringContainsString("LOCATION:Le Botanique\\, Bruxelles\r\n", $feed);
    }

    public function test_an_empty_location_or_description_is_omitted(): void
    {
        $item = $this->timedItem();
        $item->description = '';
        $item->metadata = ['location' => null];

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertStringNotContainsString('DESCRIPTION', $feed);
        $this->assertStringNotContainsString('LOCATION', $feed);
    }

    public function test_no_line_exceeds_seventy_five_octets(): void
    {
        $item = $this->timedItem();
        $item->title = str_repeat('Concert de rentree ', 20);
        $item->description = str_repeat('Une description assez longue pour forcer le pliage. ', 10);

        $feed = $this->builder->build([$item], str_repeat('Nom de groupe ', 12), $this->generatedAt);

        foreach (explode("\r\n", $feed) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), sprintf('Line over 75 octets: %s', $line));
        }
    }

    public function test_folding_never_splits_a_multibyte_character(): void
    {
        // The whole reason to fold by character rather than by byte: this app is full of accents, and
        // a cut inside a UTF-8 sequence produces a calendar the client refuses outright.
        $item = $this->timedItem();
        $item->title = str_repeat('Répétition générale à Liège ', 6);

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        $this->assertSame($feed, mb_convert_encoding($feed, 'UTF-8', 'UTF-8'));
        foreach (explode("\r\n", $feed) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), sprintf('Broken UTF-8 on: %s', $line));
        }
    }

    public function test_a_folded_line_unfolds_back_to_its_value(): void
    {
        $item = $this->timedItem();
        $item->title = str_repeat('Répétition générale ', 8);

        $feed = $this->builder->build([$item], 'Les Copains', $this->generatedAt);

        // RFC 5545 3.1: unfolding removes each CRLF and the single whitespace that follows it.
        $unfolded = str_replace("\r\n ", '', $feed);

        $this->assertStringContainsString('SUMMARY:' . $item->title . "\r\n", $unfolded);
    }

    public function test_every_event_is_wrapped_and_ordered_as_given(): void
    {
        $first = $this->timedItem();
        $first->id = 'manual-aaa';
        $first->title = 'Premier';

        $second = $this->timedItem();
        $second->id = 'manual-bbb';
        $second->title = 'Second';

        $feed = $this->builder->build([$first, $second], 'Les Copains', $this->generatedAt);

        $this->assertSame(2, substr_count($feed, "BEGIN:VEVENT\r\n"));
        $this->assertSame(2, substr_count($feed, "END:VEVENT\r\n"));
        $this->assertLessThan(strpos($feed, 'Second'), (int) strpos($feed, 'Premier'));
    }

    private function timedItem(): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'manual-3f1c';
        $item->bandSpaceId = 'b1';
        $item->source = 'manual';
        $item->sourceId = '3f1c';
        $item->datetime = '2026-08-01T20:00:00+00:00';
        $item->endDatetime = '2026-08-01T23:00:00+00:00';
        $item->isAllDay = false;
        $item->title = 'Concert';

        return $item;
    }

    private function allDayItem(): AgendaItem
    {
        $item = $this->timedItem();
        $item->isAllDay = true;

        return $item;
    }
}
