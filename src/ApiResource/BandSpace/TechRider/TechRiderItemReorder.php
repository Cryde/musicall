<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderItemReorderProcessor;
use App\Validator\BandSpace\TechRider\TechRiderItemPositions;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/reorder',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderItemResource::class, identifiers: ['riderId']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_tech_rider_items_reorder',
    processor: TechRiderItemReorderProcessor::class,
)]
#[TechRiderItemPositions]
class TechRiderItemReorder
{
    /** @var list<mixed> */
    public array $positions = [];
}
