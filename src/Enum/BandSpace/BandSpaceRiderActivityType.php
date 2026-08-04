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
    case RiderDuplicated = 'rider_duplicated';

    case RiderItemAdded = 'rider_item_added';
    case RiderItemRenamed = 'rider_item_renamed';
    case RiderItemUpdated = 'rider_item_updated';
    case RiderItemRemoved = 'rider_item_removed';
    case RiderItemReordered = 'rider_item_reordered';

    case RiderPatchListUpdated = 'rider_patch_list_updated';
    case RiderStagePlotUpdated = 'rider_stage_plot_updated';
}
