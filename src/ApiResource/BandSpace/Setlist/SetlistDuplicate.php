<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistDuplicateProcessor;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/duplicate',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SetlistResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: SetlistResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    input: false,
    output: SetlistResource::class,
    name: 'api_band_space_setlists_duplicate',
    processor: SetlistDuplicateProcessor::class,
)]
class SetlistDuplicate
{
}
