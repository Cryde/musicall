<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsRestoreProcessor;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}/restore',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: LyricsResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: LyricsResource::class, identifiers: ['id']),
    ],
    status: 200,
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    input: false,
    output: LyricsResource::class,
    read: false,
    name: 'api_band_space_lyrics_restore',
    processor: LyricsRestoreProcessor::class,
)]
class LyricsRestore
{
}
