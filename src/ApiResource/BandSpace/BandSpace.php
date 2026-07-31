<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceDeleteProcessor;
use App\State\Provider\BandSpace\BandSpaceCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceItemProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces',
            openapi: new Operation(tags: ['Band Space']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_spaces_get_collection',
            provider: BandSpaceCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{id}',
            openapi: new Operation(tags: ['Band Space']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_spaces_get_item',
            provider: BandSpaceItemProvider::class,
        ),
        // Schedules the deletion, it does not delete: app:band-space:purge removes the space and its
        // files once deletionScheduledDatetime has passed. Restore with api_band_space_restore.
        new Delete(
            uriTemplate: '/band_spaces/{id}',
            openapi: new Operation(tags: ['Band Space']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_spaces_delete',
            provider: BandSpaceItemProvider::class,
            processor: BandSpaceDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpace
{
    public string $id;
    public string $name;
    public string $role;

    /**
     * Set when the space is pending deletion, so members can be warned and admins offered a restore.
     */
    public ?string $deletionScheduledDatetime = null;
}
