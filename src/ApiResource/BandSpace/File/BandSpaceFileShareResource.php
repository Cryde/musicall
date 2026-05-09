<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\File\BandSpaceFileShareDeleteProcessor;
use App\State\Provider\BandSpace\File\BandSpaceFileShareCollectionProvider;
use App\State\Provider\BandSpace\File\BandSpaceFileShareItemProvider;

#[ApiResource(
    shortName: 'BandSpaceFileShare',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/shares',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File Share']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_shares_get_collection',
            provider: BandSpaceFileShareCollectionProvider::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/shares/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File Share']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_shares_delete',
            provider: BandSpaceFileShareItemProvider::class,
            processor: BandSpaceFileShareDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileShareResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $fileId;
    public string $fileOriginalName;
    public ?string $expiryDatetime = null;
    public ?string $revocationDatetime = null;
    public int $accessCount = 0;
    public ?string $lastAccessDatetime = null;
    public bool $hasPassword = false;
    public bool $isActive = true;
    public string $creationDatetime;

    /** @var array{id: string, username: string}|null */
    public ?array $createdBy = null;
}
