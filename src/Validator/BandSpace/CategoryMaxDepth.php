<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CategoryMaxDepth extends Constraint
{
    public const int MAX_DEPTH = 2;

    public string $message = 'band_space.finance.category.max_depth_reached';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
