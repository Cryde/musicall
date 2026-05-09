<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFileDownloadProvider;
use App\State\Provider\BandSpace\File\BandSpaceFileVersionDownloadProvider;

#[ApiResource(
    shortName: 'BandSpaceFileDownload',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{id}/download',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_files_download',
            provider: BandSpaceFileDownloadProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{id}/versions/{versionNumber}/download',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
                'versionNumber' => new Link(fromClass: self::class, identifiers: ['versionNumber']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_files_version_download',
            provider: BandSpaceFileVersionDownloadProvider::class,
        ),
    ],
)]
class BandSpaceFileDownload
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public int $versionNumber;
}
