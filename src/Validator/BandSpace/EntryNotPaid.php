<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class EntryNotPaid extends Constraint
{
    public string $message = 'band_space.finance.entry.paid_read_only';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
