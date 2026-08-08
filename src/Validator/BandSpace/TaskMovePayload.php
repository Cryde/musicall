<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

/**
 * Shape check on a move payload.
 *
 * An empty positions list is allowed and means "put the task at the end of its new column". It is
 * all a client can ask for while a server-side task filter hides part of that column from it, and
 * it is the one placement the server can work out on its own. A non-empty list has to be the whole
 * destination column, so it must carry unique ids forming a contiguous 0..n-1 sequence. Membership
 * of that column is checked by TaskColumnPositionsGuard, which knows the band space.
 */
#[\Attribute]
class TaskMovePayload extends Constraint
{
    public const string ERROR_CODE = 'music_all_a3e8b210-7c2f-4f5d-9b1e-3a8c5d6e7f8a';

    public string $invalidItemMessage = 'Chaque position doit contenir un id (UUID) et une position (entier)';
    public string $taskNotInPositionsMessage = 'La tâche déplacée doit figurer dans les positions';
    public string $duplicateIdMessage = 'Chaque tâche ne peut apparaître qu\'une seule fois';
    public string $notContiguousMessage = 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
