<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistRestoreProcessor;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/restore',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SetlistResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: SetlistResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    input: false,
    output: SetlistResource::class,
    read: false,
    name: 'api_band_space_setlists_restore',
    processor: SetlistRestoreProcessor::class,
)]
class SetlistRestore
{
}
