<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SetlistReorderPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_8a1f4c5d-7e2b-4a9c-b3d1-6f8e2c0a4b1d';

    public string $emptyMessage = 'band_space.setlist.positions_required';
    public string $invalidItemMessage = 'band_space.setlist.position_invalid_item';
    public string $notContiguousMessage = 'band_space.setlist.positions_not_contiguous';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
