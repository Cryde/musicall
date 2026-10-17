<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Whole-payload check on a patch list save.
 *
 * A class constraint rather than per-property ones because the rules that matter here span
 * rows: a channel number must be unique within its direction, and the row count is capped per
 * direction. Neither can be expressed on a single row.
 *
 * It has to run before anything is written. A save replaces the stored grid, so a payload that
 * is refused halfway would leave a band with part of a 24 row patch list, which is worse than
 * the save failing. Validation happening here, ahead of the processor, is what makes
 * "a 422 changes nothing" true by construction rather than by careful ordering.
 */
#[\Attribute]
class TechRiderPatchRows extends Constraint
{
    public const string ERROR_CODE = 'music_all_2f7c94ab-51d6-4e83-9b02-7ad3e6c81f45';

    /**
     * A rack of 64 channels per direction is already well past what a band this feature serves
     * will use, and the cap is what stops a single request persisting an unbounded grid.
     */
    public const int MAX_ROWS_PER_DIRECTION = 64;

    public const int MIN_CHANNEL = 1;
    public const int MAX_CHANNEL = 999;

    public const int MAX_NAME_LENGTH = 120;
    public const int MAX_MICROPHONE_LENGTH = 120;
    public const int MAX_ROUTING_LENGTH = 180;

    public string $tooManyRowsMessage = 'band_space.tech_rider.patch_list.too_many_rows';
    public string $invalidRowMessage = 'band_space.tech_rider.patch_list.row_invalid';
    public string $channelOutOfRangeMessage = 'band_space.tech_rider.patch_list.channel_out_of_range';
    public string $duplicateChannelMessage = 'band_space.tech_rider.patch_list.channel_duplicate';
    public string $stereoOutOfRangeMessage = 'band_space.tech_rider.patch_list.stereo_out_of_range';
    public string $invalidStereoMessage = 'band_space.tech_rider.patch_list.stereo_invalid';
    public string $tooLongMessage = 'band_space.tech_rider.patch_list.field_too_long';
    public string $invalidTextMessage = 'band_space.tech_rider.patch_list.field_not_text';
    public string $unknownColourMessage = 'band_space.tech_rider.patch_list.colour_unknown';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
