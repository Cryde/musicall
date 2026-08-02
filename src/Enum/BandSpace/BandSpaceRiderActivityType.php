<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The band_space_activity.type column is 30 characters, so every value added here
 * must stay within that. The natural names for this module run long.
 */
enum BandSpaceRiderActivityType: string
{
    case RiderCreated = 'rider_created';
    case RiderRenamed = 'rider_renamed';
    case RiderArchived = 'rider_archived';
    case RiderUnarchived = 'rider_unarchived';
}
