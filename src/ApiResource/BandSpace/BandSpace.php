<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceDeleteProcessor;
use App\State\Processor\BandSpace\BandSpaceUpdateProcessor;
use App\State\Provider\BandSpace\BandSpaceCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

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
        new Patch(
            uriTemplate: '/band_spaces/{id}',
            openapi: new Operation(tags: ['Band Space']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_spaces_patch',
            provider: BandSpaceItemProvider::class,
            processor: BandSpaceUpdateProcessor::class,
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

    /**
     * Same rules as BandSpaceCreate, so a rename cannot produce a name creation would have refused.
     * The trim normalizer makes both constraints judge the value the processor actually stores.
     */
    #[Assert\NotBlank(message: 'Veuillez spécifier un nom', normalizer: 'trim')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères',
        normalizer: 'trim'
    )]
    public string $name;
    public string $role;

    /**
     * Set when the space is pending deletion, so members can be warned and admins offered a restore.
     */
    public ?\DateTimeInterface $deletionScheduledDatetime = null;
}
