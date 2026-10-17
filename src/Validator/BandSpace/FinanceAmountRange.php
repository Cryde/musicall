<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class FinanceAmountRange extends Constraint
{
    public string $message = 'band_space.finance.amount.min_above_max';
    public string $exclusiveMessage = 'band_space.finance.amount.exact_and_range';
    public string $incompleteMessage = 'band_space.finance.amount.range_incomplete';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
