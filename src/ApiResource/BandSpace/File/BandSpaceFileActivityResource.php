<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\File\BandSpaceFileActivityCollectionProvider;

#[ApiResource(
    shortName: 'BandSpaceFileActivity',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/files/{fileId}/activities',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'fileId' => new Link(fromClass: self::class, identifiers: ['fileId']),
            ],
            openapi: new Operation(tags: ['Band Space File']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_file_activities_get_collection',
            provider: BandSpaceFileActivityCollectionProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceFileActivityResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $fileId;

    public string $actorId;
    public string $actorUsername;
    public ?string $actorProfilePictureUrl = null;
    public string $type;

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    public string $creationDatetime;
}
