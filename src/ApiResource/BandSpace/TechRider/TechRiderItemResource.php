<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\TechRider;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\TechRider\TechRiderItemDeleteProcessor;
use App\State\Processor\BandSpace\TechRider\TechRiderItemUpdateProcessor;
use App\State\Provider\BandSpace\TechRider\TechRiderItemProvider;
use App\Validator\BandSpace\TechRider\TechRiderItemContent;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'TechRiderItem',
    operations: [
        // Clients read items inline on the rider rather than one at a time, but this
        // operation is not optional: API Platform derives a resource's @id from its item Get,
        // and without one every embedded item carried a fallback
        // `/api/tech_rider_items/id=..;bandSpaceId=..;riderId=..` that routes nowhere.
        // Declaring it makes the @id real, and costs nothing beyond reusing the provider.
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [self::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_rider_items_get_item',
            provider: TechRiderItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [self::READ], 'skip_null_values' => false],
            name: 'api_band_space_tech_rider_items_patch',
            provider: TechRiderItemProvider::class,
            processor: TechRiderItemUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/tech_riders/{riderId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'riderId' => new Link(fromClass: self::class, identifiers: ['riderId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Tech Rider']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_band_space_tech_rider_items_delete',
            processor: TechRiderItemDeleteProcessor::class,
        ),
    ],
)]
class TechRiderItemResource
{
    /**
     * Also applied by TechRiderResource's item context, so an item serializes the same way
     * inline on a rider as it does from its own operations.
     */
    final const string READ = 'tech_rider_item:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $id;

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $riderId;

    #[Groups([self::READ])]
    public string $type = 'text';

    /** False keeps the item authored but out of the composed document. */
    #[Groups([self::READ])]
    public bool $isIncluded = true;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères')]
    #[Groups([self::READ])]
    public string $title;

    /** @var array<string, mixed>|null */
    #[TechRiderItemContent]
    #[Groups([self::READ])]
    public ?array $content = null;

    /**
     * The referenced file, for a Document item. Enough to render the page without a second
     * call, including whether the file has been trashed, which the item has to say out loud
     * rather than showing a blank page.
     *
     * @var array<string, mixed>|null
     */
    #[Groups([self::READ])]
    public ?array $file = null;

    /**
     * Write side of the above. Deliberately outside the read group: the response carries the
     * resolved `file` block instead, and echoing both would be two ways to say one thing.
     */
    public ?string $fileId = null;

    /**
     * The rows of a PatchList item as `{inputs: [...], outputs: [...]}`, null on every other
     * type. Two named lists rather than one list carrying a direction, because that is how the
     * page is printed and it saves the client partitioning what the server already knows.
     *
     * Written through its own PUT, not here: a patch list is replaced wholesale, and a PATCH
     * that could carry part of a grid is the shape this deliberately avoids.
     *
     * @var array<string, mixed>|null
     */
    #[Groups([self::READ])]
    public ?array $patchList = null;

    #[Assert\PositiveOrZero(message: 'La position doit être positive ou zéro')]
    #[Groups([self::READ])]
    public int $position = 0;

    #[Groups([self::READ])]
    public \DateTimeInterface $creationDatetime;

    #[Groups([self::READ])]
    public ?\DateTimeInterface $updateDatetime = null;
}
