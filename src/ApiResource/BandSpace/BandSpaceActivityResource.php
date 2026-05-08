<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\BandSpaceActivityCollectionProvider;

#[ApiResource(
    shortName: 'BandSpaceActivity',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/activities',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Activity']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_activities_get_collection',
            provider: BandSpaceActivityCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/activities/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Activity']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_activities_get_item',
            provider: BandSpaceActivityCollectionProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceActivityResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $module;
    public ?string $resourceId = null;
    public string $type;

    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    /** @var array{id: string, username: string, profile_picture_url: ?string}|null */
    public ?array $actor = null;

    public string $creationDatetime;
}
