<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SplitNotPersonal extends Constraint
{
    public string $message = 'band_space.finance.split.personal_entry';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
