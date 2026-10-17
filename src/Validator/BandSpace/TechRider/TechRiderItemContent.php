<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Bounds a item's TipTap document, and holds a text item to the rich text format (#1012).
 *
 * The column is LONGTEXT and the editor autosaves, so an accidental paste of a whole
 * document, or a pathological nesting depth, would otherwise be written without complaint.
 */
#[\Attribute]
class TechRiderItemContent extends Constraint
{
    public const string ERROR_CODE = 'music_all_3f7c1a92-58d4-4c6e-9b21-0d5a7e4f8c36';

    /** Far more prose than any rider item, far less than a leak. */
    public const int MAX_CONTENT_BYTES = 200_000;

    public const int MAX_DEPTH = 40;

    /**
     * Stated in Ko, not in characters: the limit is measured on the encoded JSON in bytes, so
     * an accented French item spends closer to two bytes a letter and a character count
     * would promise roughly twice the room actually available.
     */
    public string $tooLargeMessage = 'band_space.tech_rider.item.content_too_large';
    public string $tooDeepMessage = 'band_space.tech_rider.item.content_too_deep';
    public string $invalidMessage = 'band_space.tech_rider.item.content_invalid';
}
