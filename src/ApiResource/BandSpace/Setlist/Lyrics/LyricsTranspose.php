<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsTransposeProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** Moves every chord, and the linked song's key with them when there is a song. */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}/transpose',
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
    name: 'api_band_space_lyrics_transpose',
    processor: LyricsTransposeProcessor::class,
)]
class LyricsTranspose
{
    #[Assert\NotNull(message: 'band_space.song.transpose_required')]
    #[Assert\Range(min: -11, max: 11, notInRangeMessage: 'band_space.song.transpose_out_of_range')]
    #[Assert\NotEqualTo(value: 0, message: 'band_space.song.transpose_zero')]
    public ?int $semitones = null;

    /** Unconstrained on purpose: a missing one is LyricsWriteGuard's 428, as on PATCH. */
    public ?int $expectedVersion = null;
}
