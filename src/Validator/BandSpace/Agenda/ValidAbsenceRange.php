<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint capping how long an absence may run.
 *
 * The two dates are validated individually by `Assert\Date` and `Assert\GreaterThanOrEqual` on the
 * DTOs, the way FinanceRecurrenceCreate already validates the same `Y-m-d` string pair. Only the
 * span needs both values at once, which is the one thing a property constraint cannot express.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidAbsenceRange extends Constraint
{
    public const string RANGE_TOO_LONG_CODE = 'music_all_7a476a56-6bab-4114-82bd-3cff1ec6a8a2';

    /** A generous year, so one bad input cannot paint the whole calendar. */
    public const int MAX_DAYS = 366;

    public string $rangeTooLongMessage = 'Une indisponibilité ne peut pas dépasser 366 jours.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
