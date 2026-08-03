<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderStagePlotUpdateProcessor;
use App\Validator\BandSpace\TechRider\TechRiderStagePlot;

/**
 * A whole stage plot, sent as one document.
 *
 * PUT for the same reason the patch list uses it: the plot is replaced wholesale rather than
 * amended, and a retried save must overwrite rather than accumulate.
 *
 * Document keys are snake_case throughout, matching the rest of the API, so a violation path
 * such as `plot.stage.aspect_ratio` names a key the client actually sent.
 *
 * Element positions are fractions of the stage box, never pixels. Pixels would bind a plot to
 * whatever canvas drew it, so a phone, a desktop and an A4 page would each disagree about where
 * the drum kit stands. Fractions mean any surface at any size places elements by multiplying,
 * which is what lets the export render server side with no client generated bitmap.
 */
#[Put(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/{itemId}/stage_plot',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['riderId']),
        'itemId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => [TechRiderItemResource::READ], 'skip_null_values' => false],
    // Nothing to read: the processor resolves the item itself, as the patch list one does.
    read: false,
    output: TechRiderItemResource::class,
    name: 'api_band_space_tech_rider_stage_plot_put',
    processor: TechRiderStagePlotUpdateProcessor::class,
)]
#[TechRiderStagePlot]
class TechRiderStagePlotInput
{
    /**
     * The plot document, or null to clear it.
     *
     * Deliberately untyped beyond "an array": the validator owns the shape, and declaring parts
     * of it here would split one rule across two files.
     *
     * @var array<array-key, mixed>|null
     */
    public ?array $plot = null;
}
