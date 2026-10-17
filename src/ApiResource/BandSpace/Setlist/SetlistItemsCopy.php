<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistItemsCopyProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * « Partir d'une setlist existante » (#1062): another setlist's running order copied to the end of
 * this one, songs and intermèdes with their durations, notes and transitions.
 */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/items/copy',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: SetlistResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: SetlistResource::class, identifiers: ['id']),
    ],
    status: 200,
    openapi: new Operation(tags: ['Band Space Setlist']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: SetlistResource::class,
    read: false,
    name: 'api_band_space_setlist_items_copy',
    processor: SetlistItemsCopyProcessor::class,
)]
class SetlistItemsCopy
{
    #[Assert\NotBlank(message: 'band_space.setlist.copy_source_required')]
    #[Assert\Uuid(message: 'band_space.setlist.invalid_identifier')]
    public ?string $fromSetlistId = null;
}
