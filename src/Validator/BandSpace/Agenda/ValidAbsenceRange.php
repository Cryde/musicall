<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint on the pair of dates: their order, and how long the span may run.
 *
 * The dates themselves are the property constraints' business. They are `Y-m-d` strings carrying
 * `NotBlank` and `Assert\Date`, so a missing or malformed value is reported there and never reaches
 * here. What is left needs both values at once, which is the one thing a property constraint cannot
 * express.
 *
 * The ordering rule lives here rather than on `endDate` as a `GreaterThanOrEqual(propertyPath:)`
 * because on two strings that constraint compares them byte by byte: a start date of `not-a-date`
 * would make it fire a second, nonsensical "end before start" against a perfectly good end date,
 * and AbsencesDrawer paints it on that input. Same shape as RecurrenceEndDate on the finance side.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidAbsenceRange extends Constraint
{
    public const string END_BEFORE_START_CODE = 'music_all_d395a58d-8c7a-4b14-8367-0e0c0c4aec58';
    public const string RANGE_TOO_LONG_CODE = 'music_all_7a476a56-6bab-4114-82bd-3cff1ec6a8a2';

    /** A generous year, so one bad input cannot paint the whole calendar. */
    public const int MAX_DAYS = 366;

    public string $endBeforeStartMessage = 'La date de fin doit être postérieure ou égale à la date de début.';
    public string $rangeTooLongMessage = 'Une indisponibilité ne peut pas dépasser 366 jours.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
