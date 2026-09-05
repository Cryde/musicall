<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use DateTimeImmutable;

/**
 * Reads a bare `Y-m-d` out of a value a class-level constraint has been handed.
 *
 * Shared by ValidAbsenceRangeValidator and ValidRecurrenceValidator, which both face the same
 * problem: they answer questions *about* a date (is this pair in order, is this horizon too far out)
 * and so must not act on a value that is not one. `Assert\Date` on the property reports the format
 * mistake, but both constraints contribute to the same validation pass, so neither validator can
 * assume it has already spoken. Refusing to act is each validator's own job, and this is that check.
 */
final class CalendarDay
{
    /**
     * The day the value names, or null when it is not a bare `Y-m-d`.
     *
     * Round-tripping the parse is what does the work. It rejects an impossible calendar day, since
     * `2026-02-31` parses happily to 3 March, and a value carrying a time or an offset, which is not
     * a calendar day at all.
     *
     * The constructor rather than `createFromFormat('!Y-m-d')`: the latter raises a `ValueError` on
     * a trailing null byte (#934) where the constructor tolerates it, and a `ValueError` is not an
     * `Exception`, so it would escape the catch below as a 500.
     */
    public static function parse(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw)) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }

        return $date->format('Y-m-d') === $raw ? $date : null;
    }
}
