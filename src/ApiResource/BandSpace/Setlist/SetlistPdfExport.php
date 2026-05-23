<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Setlist\SetlistPdfExportProvider;

#[ApiResource(
    shortName: 'SetlistPdfExport',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/pdf',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_setlists_pdf_export',
            provider: SetlistPdfExportProvider::class,
        ),
    ],
)]
class SetlistPdfExport
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $id;
}
