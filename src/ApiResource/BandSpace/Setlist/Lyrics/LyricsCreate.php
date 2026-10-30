<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsCreateProcessor;
use App\State\Provider\BandSpace\Setlist\Lyrics\LyricsCreateProvider;
use App\Validator\BandSpace\LyricsSingersAreMembers;
use Symfony\Component\Validator\Constraints as Assert;

/** Read before deserializing, so a non member is refused before validation looks anything up in the space. */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/lyrics',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: LyricsResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    output: LyricsResource::class,
    read: true,
    name: 'api_band_space_lyrics_post',
    provider: LyricsCreateProvider::class,
    processor: LyricsCreateProcessor::class,
)]
class LyricsCreate
{
    #[Assert\NotBlank(message: 'common.title.required')]
    #[Assert\Length(max: 255, maxMessage: 'common.title.too_long')]
    public string $title;

    #[Assert\Length(max: SongLyrics::MAX_LENGTH, maxMessage: 'band_space.song.lyrics_too_long')]
    #[LyricsSingersAreMembers]
    public ?string $content = null;

    /** Optional: lyrics can be written before the song exists. */
    #[Assert\Uuid(message: 'band_space.song.invalid_identifier')]
    public ?string $songId = null;
}
