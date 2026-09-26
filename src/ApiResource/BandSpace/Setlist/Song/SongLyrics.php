<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongLyricsTransposeProcessor;
use App\State\Processor\BandSpace\Setlist\Song\SongLyricsUpdateProcessor;
use App\State\Provider\BandSpace\Setlist\Song\SongLyricsProvider;
use App\Validator\BandSpace\SongSingersAreMembers;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A song's lyrics, in ChordPro (#1055). Apart from the song itself so the repertoire list does not
 * carry every song's lyrics; the list only says whether there are any.
 */
#[ApiResource(
    shortName: 'SongLyrics',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}/lyrics',
            name: 'api_band_space_song_lyrics_get',
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}/lyrics',
            name: 'api_band_space_song_lyrics_patch',
            processor: SongLyricsUpdateProcessor::class,
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}/lyrics/transpose',
            status: 200,
            input: SongLyricsTranspose::class,
            read: false,
            name: 'api_band_space_song_lyrics_transpose',
            processor: SongLyricsTransposeProcessor::class,
        ),
    ],
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    provider: SongLyricsProvider::class,
)]
#[SongSingersAreMembers]
class SongLyrics
{
    public const int MAX_LENGTH = 20000;

    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\Length(max: self::MAX_LENGTH, maxMessage: 'Les paroles ne peuvent pas dépasser {{ limit }} caractères')]
    public ?string $lyrics = null;

    /** The revision of the lyrics this payload carries. The server owns it. */
    #[ApiProperty(writable: false)]
    public int $lyricsVersion = 1;

    /** The revision the caller last read, required on a write and refused when it no longer matches. */
    #[ApiProperty(readable: false)]
    public ?int $expectedLyricsVersion = null;

    /** Read only here: a transposition moves it along with the chords. */
    #[ApiProperty(writable: false)]
    public ?string $tonality = null;

    /**
     * Who the lyrics name, by the name the band knows them by: a member who left keeps their name, marked as former.
     *
     * @var list<array{id: string, name: string, is_former_member: bool}>
     */
    #[ApiProperty(writable: false)]
    public array $singers = [];
}
