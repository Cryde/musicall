<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SplitNotPersonal extends Constraint
{
    public string $message = 'La répartition n\'est pas disponible pour les entrées personnelles';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
