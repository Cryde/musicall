<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFileVersionCollectionProvider;

#[ApiResource(
    shortName: 'BandSpaceFileVersion',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/versions',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(tags: ['Band Space File Version']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_versions_get_collection',
            provider: BandSpaceFileVersionCollectionProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileVersionResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    public int $versionNumber;
    public ?int $size = null;
    public string $mimeType;
    public bool $isCurrent = false;
    public string $downloadUrl;

    /** @var array{id: string, username: string, profile_picture_url: string|null}|null */
    public ?array $createdBy = null;

    public string $creationDatetime;
}
