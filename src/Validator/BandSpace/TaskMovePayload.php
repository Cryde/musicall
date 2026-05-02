<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TaskMovePayload extends Constraint
{
    public const string ERROR_CODE = 'music_all_a3e8b210-7c2f-4f5d-9b1e-3a8c5d6e7f8a';

    public string $emptyMessage = 'Les positions sont requises';
    public string $invalidItemMessage = 'Chaque position doit contenir un id (UUID) et une position (entier)';
    public string $taskNotInPositionsMessage = 'La tâche déplacée doit figurer dans les positions';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
