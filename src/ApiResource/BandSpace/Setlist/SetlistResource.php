<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistDeleteProcessor;
use App\State\Processor\BandSpace\Setlist\SetlistUpdateProcessor;
use App\State\Provider\BandSpace\Setlist\SetlistCollectionProvider;
use App\State\Provider\BandSpace\Setlist\SetlistItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Setlist',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlists_get_collection',
            provider: SetlistCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlists_get_item',
            provider: SetlistItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlists_patch',
            provider: SetlistItemProvider::class,
            processor: SetlistUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlists_delete',
            provider: SetlistItemProvider::class,
            processor: SetlistDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class SetlistResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;

    public ?string $archiveDatetime = null;
    public string $creationDatetime;
    public ?string $updateDatetime = null;

    /** @var SetlistItemResource[] */
    #[ApiProperty(readableLink: true)]
    public array $items = [];

    public int $totalDurationSeconds = 0;
}
