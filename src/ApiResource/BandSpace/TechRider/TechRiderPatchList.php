<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderPatchListUpdateProcessor;
use App\Validator\BandSpace\TechRider\TechRiderPatchRows;

/**
 * A whole patch list, sent as one document.
 *
 * PUT rather than the POST the rest of this module uses, because this replaces a sub-resource
 * wholesale instead of asking for an action. It also has to be idempotent: the editor saves a
 * grid, and a retried request must overwrite the list rather than append a second copy of it.
 *
 * Array order is the persisted position, so the client never sends positions and cannot send an
 * order that disagrees with the list it just built.
 */
#[Put(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/{itemId}/patch_list',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['riderId']),
        'itemId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['groups' => [TechRiderItemResource::READ], 'skip_null_values' => false],
    // Nothing to read: the target is the list being replaced, and loading it would only be
    // thrown away. The processor resolves the item itself, as the reorder one does.
    read: false,
    output: TechRiderItemResource::class,
    name: 'api_band_space_tech_rider_patch_list_put',
    processor: TechRiderPatchListUpdateProcessor::class,
)]
#[TechRiderPatchRows]
class TechRiderPatchList
{
    /**
     * Not typed as a list, because a client can send a JSON object with numeric keys and the
     * deserializer will put it here verbatim. The replace procedure calls array_values for
     * exactly that reason, and claiming list<mixed> here would make that look redundant.
     *
     * @var array<array-key, mixed>
     */
    public array $inputs = [];

    /** @var array<array-key, mixed> */
    public array $outputs = [];
}
