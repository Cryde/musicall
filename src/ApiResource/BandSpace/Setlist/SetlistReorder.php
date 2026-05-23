<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistReorderProcessor;
use App\Validator\BandSpace\Setlist\SetlistReorderPositions;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/reorder',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SetlistResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: SetlistResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    status: 204,
    security: "is_granted('ROLE_USER')",
    output: false,
    name: 'api_band_space_setlists_reorder',
    processor: SetlistReorderProcessor::class,
)]
#[SetlistReorderPositions]
class SetlistReorder
{
    /** @var list<mixed> */
    public array $positions = [];
}
