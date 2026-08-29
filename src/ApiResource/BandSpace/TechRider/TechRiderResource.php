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
use App\State\Provider\BandSpace\TechRider\TechRiderProvider;
use Symfony\Component\Serializer\Attribute\Groups;
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
            normalizationContext: ['groups' => [self::LIST], 'skip_null_values' => false],
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
            normalizationContext: ['groups' => [self::ITEM, TechRiderItemResource::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_riders_get_item',
            provider: TechRiderProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [self::ITEM, TechRiderItemResource::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_riders_patch',
            provider: TechRiderProvider::class,
            processor: TechRiderUpdateProcessor::class,
        ),
        // read: false on this operation and on Delete below: TechRiderProvider would
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
            normalizationContext: ['groups' => [self::ITEM, TechRiderItemResource::READ], 'skip_null_values' => false],
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
    final const string LIST = 'tech_rider:list';
    final const string ITEM = 'tech_rider:item';

    #[ApiProperty(identifier: true)]
    #[Groups([self::LIST, self::ITEM])]
    public string $id;

    #[ApiProperty(identifier: true)]
    #[Groups([self::LIST, self::ITEM])]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    #[Groups([self::LIST, self::ITEM])]
    public string $name;

    #[Groups([self::LIST, self::ITEM])]
    public string $createdByUsername;

    #[Groups([self::LIST, self::ITEM])]
    public ?\DateTimeInterface $archiveDatetime = null;

    #[Groups([self::LIST, self::ITEM])]
    public \DateTimeInterface $creationDatetime;

    #[Groups([self::LIST, self::ITEM])]
    public ?\DateTimeInterface $updateDatetime = null;

    /**
     * Item operations only, which is why it is grouped rather than merely left empty on the
     * collection: a rider with seven items reported as `items: []` alongside
     * `item_count: 7` would be contradictory. The list omits the key entirely.
     *
     * Riders gain patch rows and a stage plot next, so the collection must never grow into
     * shipping a whole space's rider content to render a dropdown of names.
     *
     * @var TechRiderItemResource[]
     */
    #[ApiProperty(readableLink: true)]
    #[Groups([self::ITEM])]
    public array $items = [];

    #[Groups([self::LIST, self::ITEM])]
    public int $itemCount = 0;
}
