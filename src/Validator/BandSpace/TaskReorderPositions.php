<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TaskReorderPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_b7c3d2e1-5f6a-4b8c-9d0e-1f2a3b4c5d6e';

    public string $emptyMessage = 'Les positions sont requises';
    public string $invalidItemMessage = 'Chaque position doit contenir un id (UUID) et une position (entier)';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
