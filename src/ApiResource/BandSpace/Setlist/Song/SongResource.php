<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\Song\SongDeleteProcessor;
use App\State\Processor\BandSpace\Setlist\Song\SongUpdateProcessor;
use App\State\Provider\BandSpace\Setlist\Song\SongCollectionProvider;
use App\State\Provider\BandSpace\Setlist\Song\SongItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Song',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_songs_get_collection',
            provider: SongCollectionProvider::class,
            parameters: [
                'includeArchived' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                ),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_songs_get_item',
            provider: SongItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_songs_patch',
            provider: SongItemProvider::class,
            processor: SongUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/songs/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_songs_delete',
            provider: SongItemProvider::class,
            processor: SongDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class SongResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    public string $title;

    #[Assert\Range(min: 1, max: 400, notInRangeMessage: 'Le tempo doit être entre {{ min }} et {{ max }} BPM')]
    public ?int $tempo = null;

    #[Assert\Length(max: 16, maxMessage: 'La tonalité ne peut pas dépasser {{ limit }} caractères')]
    public ?string $tonality = null;

    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} secondes')]
    public ?int $referenceDuration = null;

    public ?string $notes = null;

    public ?string $archiveDatetime = null;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
