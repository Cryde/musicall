<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderDeleteProcessor;
use App\State\Processor\BandSpace\TechRider\TechRiderUnarchiveProcessor;
use App\State\Processor\BandSpace\TechRider\TechRiderUpdateProcessor;
use App\State\Provider\BandSpace\TechRider\TechRiderCollectionProvider;
use App\State\Provider\BandSpace\TechRider\TechRiderItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'TechRider',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tech_riders_get_collection',
            provider: TechRiderCollectionProvider::class,
            parameters: [
                // archived=true lists the archive instead of the live riders. Same shape as the files trash.
                'archived' => new QueryParameter(key: 'archived'),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tech_riders_get_item',
            provider: TechRiderItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_tech_riders_patch',
            provider: TechRiderItemProvider::class,
            processor: TechRiderUpdateProcessor::class,
        ),
        // read: false on this operation and on Delete below: TechRiderItemProvider would
        // hydrate a resource neither one reads, and both processors load the entity
        // themselves. Setlist still pays that cost on its Delete.
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}/unarchive',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            input: false,
            read: false,
            name: 'api_band_space_tech_riders_unarchive',
            processor: TechRiderUnarchiveProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_band_space_tech_riders_delete',
            processor: TechRiderDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class TechRiderResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;

    public ?string $createdByUsername = null;
    public ?\DateTimeInterface $archiveDatetime = null;
    public \DateTimeInterface $creationDatetime;
    public ?\DateTimeInterface $updateDatetime = null;
}
