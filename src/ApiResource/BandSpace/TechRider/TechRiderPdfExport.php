<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\TechRider\TechRiderPdfExportProvider;

/**
 * The document a rider exists to produce. A separate resource rather than an operation on
 * TechRiderResource, matching SetlistPdfExport: it returns a file, not a representation of a rider,
 * so `output: false` and the provider hands back a Response itself.
 */
#[ApiResource(
    shortName: 'TechRiderPdfExport',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}/pdf',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_tech_riders_pdf_export',
            provider: TechRiderPdfExportProvider::class,
        ),
    ],
)]
class TechRiderPdfExport
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $id;
}
