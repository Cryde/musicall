<?php declare(strict_types=1);

namespace App\Tests\Unit\Date;

use App\Date\CalendarDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * `CalendarDay::parse()` has to accept exactly what `Assert\Date` accepts, no more and no less.
 *
 * Several callers point both at the same value: the property carries `Assert\Date` and the class
 * level constraint reads it back through here. If this accepted something the constraint rejects,
 * the constraint would fire a second, redundant violation on a value already reported; if it
 * rejected something the constraint accepts, a legitimate date would be silently ignored.
 *
 * The two occurrence delete processors have no constraint behind them at all, so for those this is
 * the only thing standing between a raw URI path segment and the database.
 */
class CalendarDayTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedProvider(): iterable
    {
        yield 'an ordinary day' => ['2026-06-15'];
        yield 'the first day it is willing to name' => ['0001-01-01'];
        yield 'the last day it is willing to name' => ['9999-12-31'];
        yield 'a leap day in a leap year' => ['2024-02-29'];
        yield 'a century leap year' => ['2000-02-29'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedProvider(): iterable
    {
        // The bug this class exists for: createFromFormat raises a ValueError on a null byte, which
        // is not an Exception, so it escaped as a 500 (#934).
        yield 'a trailing null byte' => ["2026-06-15\0"];
        yield 'an embedded null byte' => ["2026-06\0-15"];

        // Both were accepted by an earlier round trip based implementation. A negative year reached
        // a DATE column that refuses it; year zero is refused by checkdate, so Assert\Date rejected
        // it while this did not.
        yield 'a negative year' => ['-0001-11-30'];
        yield 'year zero' => ['0000-06-15'];

        yield 'an impossible calendar day' => ['2026-02-31'];
        yield 'a leap day in a common year' => ['2026-02-29'];
        yield 'a century that is not a leap year' => ['1900-02-29'];
        yield 'month zero' => ['2026-00-15'];
        yield 'day zero' => ['2026-06-00'];
        yield 'month thirteen' => ['2026-13-01'];

        yield 'a five digit year' => ['10000-01-01'];
        yield 'an unpadded month' => ['2026-6-15'];
        yield 'an unpadded day' => ['2026-06-5'];
        yield 'an instant rather than a day' => ['2026-06-15T10:00:00'];
        yield 'a day carrying an offset' => ['2026-06-15+02:00'];
        yield 'a trailing newline' => ["2026-06-15\n"];
        yield 'leading whitespace' => [' 2026-06-15'];
        yield 'whitespace only' => ['   '];
        yield 'the empty string' => [''];
        yield 'a relative expression' => ['today'];
        yield 'not a date at all' => ['not-a-date'];
    }

    #[DataProvider('acceptedProvider')]
    public function test_it_accepts_a_real_calendar_day(string $raw): void
    {
        $parsed = CalendarDay::parse($raw);

        self::assertNotNull($parsed);
        // Read back as written: no offset applied, no day shifted.
        self::assertSame($raw, $parsed->format('Y-m-d'));
        self::assertSame('00:00:00', $parsed->format('H:i:s'));
    }

    #[DataProvider('rejectedProvider')]
    public function test_it_rejects_anything_that_is_not_one(string $raw): void
    {
        self::assertNull(CalendarDay::parse($raw));
    }

    /**
     * A query parameter arrives as an array when a caller writes `?from[]=x`, and a class level
     * constraint reads whatever the property holds, so a non-string has to be turned away rather
     * than reaching a parser that would raise a TypeError on it.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringProvider(): iterable
    {
        yield 'null' => [null];
        yield 'an array' => [['2026-06-15']];
        yield 'an integer' => [20260615];
        yield 'a float' => [2026.06];
        yield 'a boolean' => [true];
        yield 'an object' => [new \stdClass()];
    }

    #[DataProvider('nonStringProvider')]
    public function test_it_rejects_a_value_that_is_not_a_string(mixed $raw): void
    {
        self::assertNull(CalendarDay::parse($raw));
    }

    /**
     * Runs the real constraint, not a copy of its rule.
     *
     * The distinction is the whole value of this test. Asserting against a transcription of
     * `DateValidator`'s regex plus `checkdate()` would only prove that two things written here agree
     * with each other: the day Symfony changed the constraint, the transcription would drift with
     * the implementation and the test would stay green. Building a real validator costs nothing
     * outside the kernel and pins the thing callers actually rely on.
     */
    private static function constraintAccepts(string $raw): bool
    {
        static $validator = null;
        $validator ??= Validation::createValidator();

        return $validator->validate($raw, new Assert\Date())->count() === 0;
    }

    public function test_it_agrees_with_the_constraint_it_mirrors(): void
    {
        // Every boundary of both rules, asserted against Assert\Date itself. An earlier
        // implementation decided by round trip and quietly disagreed for year zero.
        foreach (['0000', '0001', '1000', '1900', '2000', '2024', '2026', '9999'] as $year) {
            foreach (['00', '01', '02', '06', '11', '12', '13'] as $month) {
                foreach (['00', '01', '15', '28', '29', '30', '31', '32'] as $day) {
                    $raw = $year . '-' . $month . '-' . $day;

                    self::assertSame(
                        self::constraintAccepts($raw),
                        CalendarDay::parse($raw) !== null,
                        sprintf('CalendarDay and Assert\Date disagree about "%s"', $raw),
                    );
                }
            }
        }
    }

    #[DataProvider('acceptedProvider')]
    public function test_everything_it_accepts_the_constraint_accepts_too(string $raw): void
    {
        self::assertTrue(self::constraintAccepts($raw));
    }

    /**
     * Everything rejected except the empty string, which the two deliberately disagree on and which
     * gets its own assertion below.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedNonEmptyProvider(): iterable
    {
        foreach (self::rejectedProvider() as $name => $case) {
            if ($case[0] !== '') {
                yield $name => $case;
            }
        }
    }

    #[DataProvider('rejectedNonEmptyProvider')]
    public function test_everything_it_rejects_the_constraint_rejects_too(string $raw): void
    {
        self::assertFalse(self::constraintAccepts($raw));
    }

    public function test_it_refuses_the_empty_string_where_the_constraint_abstains(): void
    {
        // The one input the two deliberately disagree on, and the reason the agreement is stated
        // over non-empty values. Every Symfony constraint returns early on an empty value, because
        // emptiness is NotBlank's job, so Assert\Date reports nothing at all for ''. CalendarDay has
        // to return something, and no day is named, so it returns null. That is what each caller
        // wants: NotBlank covers the absence dates, ValidRecurrence checks the horizon for '' itself
        // before asking for a day, and an empty URI segment is not a date under any reading.
        self::assertTrue(self::constraintAccepts(''), 'Assert\Date is expected to abstain on an empty value');
        self::assertNull(CalendarDay::parse(''));
    }
}
