<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Setlist\SetlistItemsBulkCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Several songs added in one request (#1062): « Ajouter tout le répertoire », and the repertoire's
 * « Ajouter à une setlist » (#1063). One renumbering and one activity entry, rather than one per song.
 */
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/setlists/{id}/items/bulk',
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
    name: 'api_band_space_setlist_items_bulk_post',
    processor: SetlistItemsBulkCreateProcessor::class,
)]
class SetlistItemsBulkCreate
{
    public const int MAX_SONGS = 200;

    /**
     * In the order they go into the set. The same song twice is two items, an encore.
     *
     * @var list<string>
     */
    #[Assert\Count(min: 1, max: self::MAX_SONGS, minMessage: 'Choisissez au moins un titre', maxMessage: 'Pas plus de {{ limit }} titres à la fois')]
    #[Assert\All([new Assert\Uuid(message: 'Identifiant de titre invalide')])]
    public array $songIds = [];

    /** Where the first one goes, 0 being first. Left out, or past the end, they go last. */
    #[Assert\PositiveOrZero(message: 'La position doit être positive ou zéro')]
    public ?int $position = null;
}
