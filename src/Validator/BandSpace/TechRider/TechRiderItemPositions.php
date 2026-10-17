<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Shape check on a reorder payload. Modelled on SetlistReorderPositions.
 *
 * Requires a contiguous 0..n-1 sequence, so a partial payload cannot silently renumber the
 * items it left out. Membership of the rider is checked in the processor, which is the
 * only place that knows the rider.
 */
#[\Attribute]
class TechRiderItemPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_6d2b8e14-9f37-4a5c-8e60-1c4d7b93af52';

    public string $emptyMessage = 'band_space.tech_rider.item.positions_required';
    public string $invalidItemMessage = 'band_space.tech_rider.item.position_invalid_item';
    public string $notContiguousMessage = 'band_space.tech_rider.item.positions_not_contiguous';
    public string $duplicateIdMessage = 'band_space.tech_rider.item.positions_duplicate_id';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
