<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/songs',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SongResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: SongResource::class,
    name: 'api_band_space_songs_post',
    processor: SongCreateProcessor::class,
)]
class SongCreate
{
    #[Assert\NotBlank(message: 'common.title.required')]
    #[Assert\Length(max: 255, maxMessage: 'common.title.too_long')]
    public string $title;

    #[Assert\Range(min: 1, max: 400, notInRangeMessage: 'band_space.song.tempo_out_of_range')]
    public ?int $tempo = null;

    #[Assert\Length(max: 16, maxMessage: 'band_space.song.key_too_long')]
    public ?string $tonality = null;

    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'band_space.song.duration_out_of_range')]
    public ?int $referenceDuration = null;

    public ?string $notes = null;
}
