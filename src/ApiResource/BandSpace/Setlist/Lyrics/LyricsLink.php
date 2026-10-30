<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsLinkProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}/link',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: LyricsResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: LyricsResource::class, identifiers: ['id']),
    ],
    status: 200,
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    output: LyricsResource::class,
    read: false,
    name: 'api_band_space_lyrics_link',
    processor: LyricsLinkProcessor::class,
)]
class LyricsLink
{
    #[Assert\NotBlank(message: 'band_space.lyrics.song_required')]
    #[Assert\Uuid(message: 'band_space.song.invalid_identifier')]
    public ?string $songId = null;
}
