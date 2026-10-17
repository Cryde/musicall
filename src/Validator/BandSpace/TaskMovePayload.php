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

    public string $invalidItemMessage = 'band_space.task.position_invalid_item';
    public string $taskNotInPositionsMessage = 'band_space.task.moved_task_not_in_positions';
    public string $duplicateIdMessage = 'band_space.task.positions_duplicate_id';
    public string $notContiguousMessage = 'band_space.task.positions_not_contiguous';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
