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
use App\State\Processor\BandSpace\File\BandSpaceFileTagDeleteProcessor;
use App\State\Processor\BandSpace\File\BandSpaceFileTagUpdateProcessor;
use App\State\Provider\BandSpace\File\BandSpaceFileTagCollectionProvider;
use App\State\Provider\BandSpace\File\BandSpaceFileTagItemProvider;

#[ApiResource(
    shortName: 'BandSpaceFileTag',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/tags',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File Tag']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_tags_get_collection',
            provider: BandSpaceFileTagCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tags/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File Tag']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_tags_get_item',
            provider: BandSpaceFileTagItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tags/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File Tag']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_tags_patch',
            provider: BandSpaceFileTagItemProvider::class,
            processor: BandSpaceFileTagUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tags/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File Tag']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_tags_delete',
            provider: BandSpaceFileTagItemProvider::class,
            processor: BandSpaceFileTagDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileTagResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $name;
    public ?string $colorHex = null;
    public int $fileCount = 0;
    public string $creationDatetime;
}
