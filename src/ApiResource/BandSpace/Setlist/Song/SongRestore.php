<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongRestoreProcessor;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}/restore',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SongResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: SongResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    input: false,
    output: SongResource::class,
    read: false,
    name: 'api_band_space_songs_restore',
    processor: SongRestoreProcessor::class,
)]
class SongRestore
{
}
