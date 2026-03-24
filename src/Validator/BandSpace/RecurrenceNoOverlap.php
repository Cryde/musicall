<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RecurrenceNoOverlap extends Constraint
{
    public string $message = 'Une récurrence avec le même intervalle existe déjà sur cette période pour cette catégorie';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
