<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CategoryMaxDepth extends Constraint
{
    public const int MAX_DEPTH = 2;

    public string $message = 'La profondeur maximale de {{ limit }} niveaux est atteinte';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
