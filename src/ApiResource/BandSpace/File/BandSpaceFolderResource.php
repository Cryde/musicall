<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFolderDeleteProcessor;
use App\State\Processor\BandSpace\File\BandSpaceFolderUpdateProcessor;
use App\State\Provider\BandSpace\File\BandSpaceFolderCollectionProvider;
use App\State\Provider\BandSpace\File\BandSpaceFolderItemProvider;

#[ApiResource(
    shortName: 'BandSpaceFolder',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/folders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Folder']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_folders_get_collection',
            provider: BandSpaceFolderCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Folder']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_folders_get_item',
            provider: BandSpaceFolderItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Folder']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_folders_patch',
            provider: BandSpaceFolderItemProvider::class,
            processor: BandSpaceFolderUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/folders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Folder']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_folders_delete',
            provider: BandSpaceFolderItemProvider::class,
            processor: BandSpaceFolderDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFolderResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $name;
    public ?string $parentId = null;
    public int $depth = 0;

    /**
     * Inlined nested folder shapes. Same fields as the top-level resource —
     * typed as a generic array so API Platform serializes them as embedded
     * objects rather than IRI references.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $children = [];

    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
