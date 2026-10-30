<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Setlist\Lyrics\LyricsCollectionProvider;

/** The « Paroles » list: what each text is and where it is linked, without the text itself. */
#[ApiResource(
    shortName: 'LyricsListItem',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/lyrics',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_lyrics_get_collection',
            provider: LyricsCollectionProvider::class,
            parameters: [
                'archived' => new QueryParameter(key: 'archived'),
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class LyricsListItem
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $title;
    public bool $hasContent = false;

    #[ApiProperty(genId: false)]
    public ?LyricsSongInfo $song = null;

    public ?\DateTimeInterface $archiveDatetime = null;
    public \DateTimeInterface $creationDatetime;
    public ?\DateTimeInterface $updateDatetime = null;
}
