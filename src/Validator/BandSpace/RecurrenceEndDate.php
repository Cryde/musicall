<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RecurrenceEndDate extends Constraint
{
    public const int MAX_YEARS = 3;

    public string $messageBeforeStart = 'band_space.finance.recurrence.end_before_start';
    public string $messageMaxDuration = 'band_space.finance.recurrence.max_duration';
    public string $messageInvalidFormat = 'band_space.finance.date_invalid';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
