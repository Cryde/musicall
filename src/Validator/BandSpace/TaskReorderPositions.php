<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

/**
 * Shape check on a reorder payload. Modelled on TechRiderItemPositions.
 *
 * Requires unique ids forming a contiguous 0..n-1 sequence, so a payload cannot leave two tasks
 * of a column sharing a position. Membership of the column is checked by TaskColumnPositionsGuard,
 * which is the only thing that knows the band space.
 */
#[\Attribute]
class TaskReorderPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_b7c3d2e1-5f6a-4b8c-9d0e-1f2a3b4c5d6e';

    public string $emptyMessage = 'band_space.task.positions_required';
    public string $invalidItemMessage = 'band_space.task.position_invalid_item';
    public string $duplicateIdMessage = 'band_space.task.positions_duplicate_id';
    public string $notContiguousMessage = 'band_space.task.positions_not_contiguous';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
