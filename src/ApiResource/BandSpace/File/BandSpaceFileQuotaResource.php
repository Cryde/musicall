<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFileQuotaProvider;

#[ApiResource(
    shortName: 'BandSpaceFileQuota',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/quota',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_files_quota_get',
            provider: BandSpaceFileQuotaProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileQuotaResource
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public int $quotaBytes;
    public int $usedBytes;
    public float $usedPercentage;
    public bool $isApproachingLimit;

    /** @var array<int, array{source: string, bytes: int}> */
    public array $breakdownBySource = [];
}
