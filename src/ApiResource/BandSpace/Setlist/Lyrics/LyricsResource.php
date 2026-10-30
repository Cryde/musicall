<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsDeleteProcessor;
use App\State\Processor\BandSpace\Setlist\Lyrics\LyricsUpdateProcessor;
use App\State\Provider\BandSpace\Setlist\Lyrics\LyricsItemProvider;
use App\Validator\BandSpace\LyricsSingersAreMembers;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lyrics on their own (#1260), so they can be written before the song exists and linked to it later.
 * A song's lyrics stay reachable through SongLyrics, which the mobile app reads.
 */
#[ApiResource(
    shortName: 'Lyrics',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}',
            name: 'api_band_space_lyrics_get_item',
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}',
            name: 'api_band_space_lyrics_patch',
            processor: LyricsUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/lyrics/{id}',
            read: false,
            name: 'api_band_space_lyrics_delete',
            processor: LyricsDeleteProcessor::class,
        ),
    ],
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: self::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    normalizationContext: ['skip_null_values' => false],
    security: "is_granted('ROLE_USER')",
    provider: LyricsItemProvider::class,
)]
class LyricsResource
{
    #[ApiProperty(identifier: true, writable: false)]
    public string $id;

    #[ApiProperty(identifier: true, writable: false)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'common.title.required')]
    #[Assert\Length(max: 255, maxMessage: 'common.title.too_long')]
    public string $title;

    /** ChordPro, as on SongLyrics. */
    #[Assert\Length(max: SongLyrics::MAX_LENGTH, maxMessage: 'band_space.song.lyrics_too_long')]
    #[LyricsSingersAreMembers]
    public ?string $content = null;

    /** The revision of the content this payload carries. The server owns it. */
    #[ApiProperty(writable: false)]
    public int $version = 1;

    /** The revision the caller last read, required on a content write and refused when it no longer matches. */
    #[ApiProperty(readable: false)]
    public ?int $expectedVersion = null;

    #[ApiProperty(writable: false)]
    public bool $hasContent = false;

    /** Changed through link and unlink only. */
    #[ApiProperty(writable: false, genId: false)]
    public ?LyricsSongInfo $song = null;

    /**
     * @var list<array{id: string, name: string, is_former_member: bool}>
     */
    #[ApiProperty(writable: false)]
    public array $singers = [];

    #[ApiProperty(writable: false)]
    public ?\DateTimeInterface $archiveDatetime = null;

    #[ApiProperty(writable: false)]
    public \DateTimeInterface $creationDatetime;

    #[ApiProperty(writable: false)]
    public ?\DateTimeInterface $updateDatetime = null;
}
