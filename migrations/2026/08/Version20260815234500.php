<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Converts the historical datetimes that were written as Europe/Brussels wall clock into UTC.
 *
 * Until #888 the web tier ran `date.timezone = "Europe/Brussels"` while the CLI tier ran UTC. Doctrine
 * writes a DATETIME using the object's own timezone but reads it back using PHP's default, so every
 * value written by a web request through an implicit `new DateTime()` is Brussels wall clock. Now that
 * both tiers are UTC those rows read two hours late.
 *
 * Only six columns are converted, out of 120 DATETIME columns. A two hour shift is invisible unless it
 * changes a day a user reads, and all rendering goes through the five helpers in assets/js/utils/date.js.
 * The audit behind this list is in #889 and tasks/datetime-utc-conversion-audit.md. Within those six,
 * every pre-flip row is converted rather than only the ones whose day changes: leaving the rest would
 * make the column hold a mix of Brussels and UTC values, which is worse than being uniformly wrong.
 *
 * Deliberately NOT converted, each for a reason that would be a bug to ignore:
 *  - agenda_entry.event_datetime and end_datetime are already true UTC, written from explicit-Z
 *    payloads. Converting them would reintroduce the bug #888 fixed.
 *  - task.due_date and finance_recurrence.start_date / end_date are DATETIME columns holding a midnight
 *    that means a date. Converting midnight Brussels yields 22:00 the previous day, moving the date
 *    itself. Confirmed on production: 8 of 8 task.due_date rows are exactly midnight.
 *  - The Band Space file and tech rider columns measured 8 rows with 0 day changes, and 0 rows.
 *
 * The conversion is done in PHP rather than with MySQL's CONVERT_TZ, deliberately. Production does not
 * have the MySQL timezone tables loaded, so CONVERT_TZ returns NULL there, and an UPDATE built on it
 * would have blanked all six columns. Beyond that hazard, PHP's timezone database is what wrote these
 * values in the first place, so using it to read them back is symmetric, and it behaves identically in
 * dev, CI and production. Given this whole incident was caused by dev and production disagreeing, the
 * approach that cannot diverge is the right one.
 *
 * Note the two implementations do not even agree with each other on the autumn ambiguous hour:
 * 2026-10-25 02:30 Brussels resolves to 00:30 UTC under MySQL and 01:30 UTC under PHP. Both are
 * defensible, which is precisely why only one of them should ever be used.
 */
final class Version20260815234500 extends AbstractMigration
{
    /**
     * Everything stored before this instant is pre-flip and therefore Brussels wall clock.
     *
     * The flip happened late on 2026-08-15. A pre-flip row written at instant T stores T+2h while a
     * post-flip row stores T, so stored values in the two hours after the flip are ambiguous and cannot
     * be told apart by timestamp alone. Cutting at midnight on the 15th sidesteps that entirely: it
     * converts only rows that are unambiguously pre-flip, at the cost of skipping any row written during
     * 2026-08-15 itself.
     *
     * That skipped window is not assumed to be empty, it is reported: reportSkippedWindow() counts what
     * falls in it and warns, because a row there stays two hours wrong with no other signal.
     */
    private const string CUTOFF = '2026-08-15 00:00:00';

    /**
     * The tier flip happened late on 2026-08-15, bracketed to roughly 20:33 to 21:53 UTC by the comment
     * timestamps on #888. Anything stored on the 15th at or after this is certainly post-flip and needs
     * no conversion; anything before it is the genuinely ambiguous remainder worth a human look.
     */
    private const string FLIP_LOWER_BOUND = '2026-08-15 20:00:00';

    private const string CIVIL_TIMEZONE = 'Europe/Brussels';

    /** Rows per generated UPDATE. Keeps the statement count and each statement's size both sane. */
    private const int BATCH_SIZE = 500;

    /**
     * table, primary key column, datetime column.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const array TARGETS = [
        ['publication', 'id', 'publication_datetime'],
        ['publication', 'id', 'creation_datetime'],
        ['publication', 'id', 'edition_datetime'],
        ['forum_post', 'id', 'creation_datetime'],
        ['forum_topic', 'id', 'creation_datetime'],
        ['musician_announce', 'id', 'creation_datetime'],
    ];

    public function getDescription(): string
    {
        return 'Convert historical Brussels-written datetimes to UTC on the six columns that render a date';
    }

    public function up(Schema $schema): void
    {
        $this->reportSkippedWindow();
        $this->convertAll(self::CIVIL_TIMEZONE, 'UTC');
    }

    public function down(Schema $schema): void
    {
        // The same cutoff still selects exactly the rows up() converted: conversion only ever lowers a
        // value, so nothing it touched can have crossed above the cutoff, and nothing it skipped can
        // have dropped below it.
        //
        // convertAll() runs the same per-row round trip check on the way back, so down() is guarded as
        // strictly as up() rather than relying on up() having vetted the data earlier.
        $this->convertAll('UTC', self::CIVIL_TIMEZONE);
    }

    /**
     * Names the rows the cutoff deliberately skips, instead of asserting the window is empty.
     *
     * A row written on 2026-08-15 before the tier flip is pre-flip Brussels data that this migration
     * will not touch, so it stays two hours wrong and nothing else will ever flag it. The expectation is
     * zero rows, at roughly one publication every three days, but production has kept accumulating since
     * the audit and an expectation is not a check.
     *
     * This warns rather than aborts on purpose: a handful of rows in that window is a nuisance to fix by
     * hand, not a reason to block the other ten thousand from being corrected.
     */
    private function reportSkippedWindow(): void
    {
        foreach (self::TARGETS as [$table, , $column]) {
            $skipped = (int) $this->connection->fetchOne(sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s >= ? AND %s < ?',
                $table,
                $column,
                $column,
            ), [self::CUTOFF, self::FLIP_LOWER_BOUND]);

            $this->warnIf(
                $skipped > 0,
                sprintf(
                    '%s.%s has %d row(s) stored between %s and the tier flip. They are pre-flip Brussels '
                    . 'values that this migration deliberately does not convert, because that window '
                    . 'cannot be told apart from post-flip UTC data by timestamp. They will remain two '
                    . 'hours early and need fixing by hand.',
                    $table,
                    $column,
                    $skipped,
                    self::CUTOFF,
                )
            );
        }
    }

    private function convertAll(string $from, string $to): void
    {
        $fromZone = new DateTimeZone($from);
        $toZone = new DateTimeZone($to);

        foreach (self::TARGETS as [$table, $primaryKey, $column]) {
            $rows = $this->connection->fetchAllAssociative(sprintf(
                'SELECT %s AS pk, %s AS value FROM %s WHERE %s IS NOT NULL AND %s < ?',
                $primaryKey,
                $column,
                $table,
                $column,
                $column,
            ), [self::CUTOFF]);

            $converted = [];
            foreach ($rows as $row) {
                $stored = (string) $row['value'];
                $converted[(string) $row['pk']] = $this->shift($stored, $fromZone, $toZone, $table, $column);
            }

            foreach (array_chunk($converted, self::BATCH_SIZE, true) as $chunk) {
                $this->addSql($this->buildUpdate($table, $primaryKey, $column, $chunk));
            }
        }
    }

    /**
     * Reinterprets a stored wall clock in $from and re-expresses it in $to, refusing anything that
     * would not survive the reverse trip.
     *
     * The case that breaks reversibility is a wall clock inside a spring forward gap, for example
     * 02:30 on 2026-03-29, an hour that never existed in Brussels. PHP resolves it forward, so the
     * original bytes cannot be recovered and down() would silently not restore that row. Verified:
     *
     *   2026-03-29 02:30 -> 01:30 UTC -> 03:30   (not equal to the original)
     *   2026-10-25 02:30 -> 01:30 UTC -> 02:30   (the autumn ambiguous hour is fine)
     *
     * Such a row should not exist, because `new DateTime()` cannot produce a local time that does not
     * exist. But this database has been accumulating rows since 2008 through several stacks, so the
     * assumption is checked rather than trusted, on every row, before any SQL is queued.
     */
    private function shift(string $stored, DateTimeZone $from, DateTimeZone $to, string $table, string $column): string
    {
        $result = (new DateTimeImmutable($stored, $from))->setTimezone($to)->format('Y-m-d H:i:s');
        $roundTrip = (new DateTimeImmutable($result, $to))->setTimezone($from)->format('Y-m-d H:i:s');

        $this->abortIf(
            $roundTrip !== $stored,
            sprintf(
                '%s.%s holds the value "%s", which does not survive a %s to %s round trip (it comes back '
                . 'as "%s"). That is almost certainly a wall clock inside a spring forward gap. Converting '
                . 'it would make down() lossy. Inspect that row before running this migration.',
                $table,
                $column,
                $stored,
                $from->getName(),
                $to->getName(),
                $roundTrip,
            )
        );

        return $result;
    }

    /**
     * One UPDATE per chunk, using CASE so a batch is a single statement.
     *
     * Values go through the connection's quoting rather than string interpolation. The identifiers come
     * from the private const above and are never request data, but the primary keys and datetimes are
     * read from the database and are quoted accordingly. Primary keys are a mix of INT (publication) and
     * CHAR(36) UUIDs (the rest), and quoting both is correct for either.
     *
     * @param array<string, string> $chunk primary key => converted datetime
     */
    private function buildUpdate(string $table, string $primaryKey, string $column, array $chunk): string
    {
        $cases = '';
        $ids = [];

        foreach ($chunk as $pk => $value) {
            $quotedPk = $this->connection->quote((string) $pk);
            $cases .= sprintf(' WHEN %s THEN %s', $quotedPk, $this->connection->quote($value));
            $ids[] = $quotedPk;
        }

        return sprintf(
            'UPDATE %s SET %s = CASE %s%s END WHERE %s IN (%s)',
            $table,
            $column,
            $primaryKey,
            $cases,
            $primaryKey,
            implode(', ', $ids),
        );
    }
}
