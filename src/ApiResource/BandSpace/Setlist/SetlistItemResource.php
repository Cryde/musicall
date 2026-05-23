<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\BandSpace\SetlistItemType;
use App\State\Processor\BandSpace\Setlist\SetlistItemDeleteProcessor;
use App\State\Processor\BandSpace\Setlist\SetlistItemUpdateProcessor;
use App\State\Provider\BandSpace\Setlist\SetlistItemReadProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'SetlistItem',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{setlistId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'setlistId' => new Link(fromClass: self::class, identifiers: ['setlistId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlist_items_get',
            provider: SetlistItemReadProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{setlistId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'setlistId' => new Link(fromClass: self::class, identifiers: ['setlistId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlist_items_patch',
            provider: SetlistItemReadProvider::class,
            processor: SetlistItemUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{setlistId}/items/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'setlistId' => new Link(fromClass: self::class, identifiers: ['setlistId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Setlist']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_setlist_items_delete',
            provider: SetlistItemReadProvider::class,
            processor: SetlistItemDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class SetlistItemResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $setlistId;

    public SetlistItemType $type;

    /** Compact song info, null when type !== song or song was hard-deleted. */
    #[ApiProperty(genId: false)]
    public ?SetlistItemSongInfo $song = null;

    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    public ?string $label = null;

    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} secondes')]
    public ?int $durationOverride = null;

    public ?string $note = null;

    #[Assert\Length(max: 50, maxMessage: 'La transition ne peut pas dépasser {{ limit }} caractères')]
    public ?string $transition = null;

    public int $position = 0;
}
