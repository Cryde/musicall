<?php

declare(strict_types=1);

namespace App\Date;

use DateTimeImmutable;

/**
 * Reads a bare `Y-m-d` out of an untrusted value.
 *
 * Used by the two agenda class-level constraints, which must not act on a value the property's own
 * `Assert\Date` has already rejected, and by the two occurrence delete processors, which read a day
 * out of a URI path segment where nothing has validated anything at all.
 */
final class CalendarDay
{
    /**
     * The day the value names, or null when it is not a bare, real `Y-m-d`.
     *
     * This is `Assert\Date`'s rule, reimplemented rather than approximated: the same anchored
     * pattern and the same `checkdate()`. Callers point both at one value, so the two have to agree
     * on every non-empty input; CalendarDayTest asserts that against the real constraint. The empty
     * string is the deliberate exception: constraints abstain on it because emptiness is NotBlank's
     * job, while this has to return something, and no day is named.
     *
     * Not `createFromFormat('!Y-m-d')`, which raises a `ValueError` on a null byte (#934) where the
     * constructor tolerates it, and a `ValueError` is not an `Exception`. Deciding by round trip
     * instead of `checkdate()` was wrong twice over: `-0001-11-30` and `0000-06-15` both round trip
     * byte for byte, and `Assert\Date` refuses both.
     */
    public static function parse(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw)) {
            return null;
        }

        // `\z` rather than `$`, which also matches before a trailing newline.
        if (preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})\z/', $raw, $parts) !== 1) {
            return null;
        }

        if (!checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year'])) {
            return null;
        }

        return new DateTimeImmutable($raw);
    }
}
