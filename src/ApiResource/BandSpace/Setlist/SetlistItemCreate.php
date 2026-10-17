<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\BandSpace\SetlistItemType;
use App\State\Processor\BandSpace\Setlist\SetlistItemCreateProcessor;
use App\Validator\BandSpace\Setlist\ValidSetlistItemPayload;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{setlistId}/items',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SetlistItemResource::class, identifiers: ['bandSpaceId']),
        'setlistId' => new Link(fromClass: SetlistItemResource::class, identifiers: ['setlistId']),
    ],
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: SetlistItemResource::class,
    name: 'api_band_space_setlist_items_post',
    processor: SetlistItemCreateProcessor::class,
)]
#[ValidSetlistItemPayload]
class SetlistItemCreate
{
    #[Assert\NotNull(message: 'band_space.setlist.item.type_required')]
    public ?SetlistItemType $type = null;

    /** Required when type === song; rejected otherwise. Validated by ValidSetlistItemPayload. */
    public ?string $songId = null;

    /** Required when type !== song; rejected when type === song. Validated by ValidSetlistItemPayload. */
    #[Assert\Length(max: 255, maxMessage: 'common.label.too_long')]
    public ?string $label = null;

    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'band_space.setlist.item.duration_out_of_range')]
    public ?int $durationOverride = null;

    public ?string $note = null;

    #[Assert\Length(max: 50, maxMessage: 'band_space.setlist.item.transition_too_long')]
    public ?string $transition = null;

    /** Where to insert, 0 being first (#1061). Left out, or past the end, the item goes last. */
    #[Assert\PositiveOrZero(message: 'common.position.positive_or_zero')]
    public ?int $position = null;
}
