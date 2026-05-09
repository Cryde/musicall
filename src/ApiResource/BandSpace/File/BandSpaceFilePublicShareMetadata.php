<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFilePublicShareMetadataProvider;

#[ApiResource(
    shortName: 'BandSpaceFilePublicShareMetadata',
    operations: [
        new Get(
            uriTemplate: '/shares/{token}/metadata',
            openapi: new Operation(tags: ['Band Space File Share']),
            name: 'api_band_space_file_shares_public_metadata',
            provider: BandSpaceFilePublicShareMetadataProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFilePublicShareMetadata
{
    #[ApiProperty(identifier: true)]
    public string $token;

    public string $originalName;
    public ?int $size = null;
    public ?string $mimeType = null;
    public string $expiryDatetime;
    public bool $hasPassword = false;
}
