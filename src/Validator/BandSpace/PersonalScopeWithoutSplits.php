<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PersonalScopeWithoutSplits extends Constraint
{
    public string $message = 'band_space.finance.entry.personal_with_splits';
    public string $recurrenceMessage = 'band_space.finance.recurrence.personal_with_splits';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
