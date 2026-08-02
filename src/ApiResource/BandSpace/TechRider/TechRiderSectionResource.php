<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderSectionDeleteProcessor;
use App\State\Processor\BandSpace\TechRider\TechRiderSectionUpdateProcessor;
use App\State\Provider\BandSpace\TechRider\TechRiderSectionItemProvider;
use App\Validator\BandSpace\TechRider\TechRiderSectionContent;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'TechRiderSection',
    operations: [
        // Clients read sections inline on the rider rather than one at a time, but this
        // operation is not optional: API Platform derives a resource's @id from its item Get,
        // and without one every embedded section carried a fallback
        // `/api/tech_rider_sections/id=..;bandSpaceId=..;riderId=..` that routes nowhere.
        // Declaring it makes the @id real, and costs nothing beyond reusing the provider.
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/sections/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [self::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_rider_sections_get_item',
            provider: TechRiderSectionItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/sections/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [self::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_rider_sections_patch',
            provider: TechRiderSectionItemProvider::class,
            processor: TechRiderSectionUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/sections/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_band_space_tech_rider_sections_delete',
            processor: TechRiderSectionDeleteProcessor::class,
        ),
    ],
)]
class TechRiderSectionResource
{
    /**
     * Also applied by TechRiderResource's item context, so a section serializes the same way
     * inline on a rider as it does from its own operations.
     */
    final const string READ = 'tech_rider_section:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $id;

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $riderId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    #[Groups([self::READ])]
    public string $title;

    /** @var array<string, mixed>|null */
    #[TechRiderSectionContent]
    #[Groups([self::READ])]
    public ?array $content = null;

    #[Assert\PositiveOrZero(message: 'La position doit être positive ou zéro')]
    #[Groups([self::READ])]
    public int $position = 0;

    #[Groups([self::READ])]
    public \DateTimeInterface $creationDatetime;

    #[Groups([self::READ])]
    public ?\DateTimeInterface $updateDatetime = null;
}
