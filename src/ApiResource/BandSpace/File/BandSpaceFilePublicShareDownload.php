<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFilePublicShareDownloadProvider;

#[ApiResource(
    shortName: 'BandSpaceFilePublicShareDownload',
    operations: [
        new Get(
            uriTemplate: '/shares/{token}/download',
            openapi: new Operation(tags: ['Band Space File Share']),
            output: false,
            name: 'api_band_space_file_shares_public_download',
            provider: BandSpaceFilePublicShareDownloadProvider::class,
        ),
    ],
)]
class BandSpaceFilePublicShareDownload
{
    #[ApiProperty(identifier: true)]
    public string $token;
}
