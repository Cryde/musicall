<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Whole-document check on a stage plot.
 *
 * The plot is raw JSON authored by a canvas editor, so this is a real boundary: every number is
 * something a dragging bug could put out of range, and the document is read back by an export
 * that has no opportunity to ask questions. Unknown keys are refused rather than ignored, so a
 * client sending `posX` gets a 422 instead of a plot that silently lost its positions.
 */
#[\Attribute]
class TechRiderStagePlot extends Constraint
{
    public const string ERROR_CODE = 'music_all_4c1e7a92-3b58-4d0f-9e14-2a6f8b35d7c0';

    /**
     * Present from the first document written. A later schema change is then a migration over
     * known shapes rather than a guess about what an old plot meant.
     */
    public const int SCHEMA_VERSION = 1;

    public const int MAX_ELEMENTS = 120;
    public const int MAX_LEGEND_ENTRIES = 20;
    public const int MAX_LABEL_LENGTH = 60;

    /**
     * The element id is a short client generated key, not content. Bounded because this constraint
     * is the only one on the plot: unlike a generic content write there is no byte ceiling behind
     * it, so 120 unbounded strings would be 120 unbounded strings.
     */
    public const int MAX_ELEMENT_ID_LENGTH = 64;

    public const float MIN_SCALE = 0.25;
    public const float MAX_SCALE = 4.0;

    public const float MIN_ASPECT_RATIO = 0.5;
    public const float MAX_ASPECT_RATIO = 3.0;

    /**
     * Any whole degree. A stage is not built on a grid: wedges angle in towards a performer and a
     * riser sits across a corner, so quarter turns produced a diagram that read as approximate.
     *
     * Whole degrees rather than fractions, and 0 to 359 rather than any integer, for the same
     * reason: one representation per angle. 360 and 0 are the same rotation, so allowing both would
     * mean two documents that draw identically no longer compare identically.
     */
    public const int MIN_ROTATION = 0;
    public const int MAX_ROTATION = 359;

    public string $invalidDocumentMessage = 'band_space.tech_rider.stage_plot.not_an_object';
    public string $unknownKeyMessage = 'band_space.tech_rider.stage_plot.unknown_field';
    public string $wrongVersionMessage = 'band_space.tech_rider.stage_plot.version_unsupported';
    public string $invalidListMessage = 'band_space.tech_rider.stage_plot.not_a_list';
    public string $tooManyElementsMessage = 'band_space.tech_rider.stage_plot.too_many_elements';
    public string $tooManyLegendEntriesMessage = 'band_space.tech_rider.stage_plot.too_many_legend_entries';
    public string $invalidEntryMessage = 'band_space.tech_rider.stage_plot.entry_malformed';
    public string $tooLongIdMessage = 'band_space.tech_rider.stage_plot.id_too_long';
    public string $duplicateIdMessage = 'band_space.tech_rider.stage_plot.id_duplicate';
    public string $coordinateOutOfRangeMessage = 'band_space.tech_rider.stage_plot.position_out_of_range';
    public string $scaleOutOfRangeMessage = 'band_space.tech_rider.stage_plot.scale_out_of_range';
    public string $invalidRotationMessage = 'band_space.tech_rider.stage_plot.rotation_invalid';
    public string $tooLongLabelMessage = 'common.label.too_long';
    public string $unknownIconMessage = 'band_space.tech_rider.stage_plot.icon_unknown';
    public string $unknownColourMessage = 'band_space.tech_rider.stage_plot.colour_unknown';
    public string $aspectRatioOutOfRangeMessage = 'band_space.tech_rider.stage_plot.aspect_ratio_out_of_range';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
