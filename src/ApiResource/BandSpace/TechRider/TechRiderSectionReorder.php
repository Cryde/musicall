<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderSectionReorderProcessor;
use App\Validator\BandSpace\TechRider\TechRiderSectionPositions;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/sections/reorder',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: TechRiderSectionResource::class, identifiers: ['bandSpaceId']),
        'riderId' => new Link(fromClass: TechRiderSectionResource::class, identifiers: ['riderId']),
    ],
    openapi: new Operation(tags: ['Band Space Tech Rider']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_tech_rider_sections_reorder',
    processor: TechRiderSectionReorderProcessor::class,
)]
#[TechRiderSectionPositions]
class TechRiderSectionReorder
{
    /** @var list<mixed> */
    public array $positions = [];
}
