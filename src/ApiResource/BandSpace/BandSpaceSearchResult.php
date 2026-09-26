<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\State\Provider\BandSpace\BandSpaceSearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'BandSpaceSearchResult',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/search',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Search']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_search_get_collection',
            provider: BandSpaceSearchProvider::class,
            parameters: [
                'q' => new QueryParameter(key: 'q'),
                // One kind only (#1046): with a query, up to 20 hits of it; without one, its 5 most
                // recently created or edited items.
                'type' => new QueryParameter(key: 'type', constraints: [
                    new Assert\Choice(callback: [BandSpaceSearchResultType::class, 'values'], message: 'Type de résultat inconnu'),
                ]),
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceSearchResult
{
    /**
     * Synthetic, '<type>-<uuid>', because the collection mixes seven entities and JSON-LD needs one
     * identifier per member. Same shape as AgendaItem's 'manual-<uuid>'.
     */
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /** A BandSpaceSearchResultType value. */
    public string $type;

    /** The matched record's own id, which is what the frontend deep links to. */
    public string $resourceId;

    public string $title;

    /** Whatever situates the hit: a date, a category, a parent note, a folder. */
    public ?string $subtitle = null;
}
