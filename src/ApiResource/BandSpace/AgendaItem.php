<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\AgendaCollectionProvider;

#[ApiResource(
    shortName: 'AgendaItem',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/agenda',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Agenda']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_agenda_get_collection',
            provider: AgendaCollectionProvider::class,
            parameters: [
                'from' => new QueryParameter(key: 'from'),
                'to' => new QueryParameter(key: 'to'),
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class AgendaItem
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $source;
    public string $sourceId;
    public string $datetime;
    public ?string $endDatetime = null;
    public bool $isAllDay = false;
    public string $title;
    public ?string $description = null;

    /** @var array<string, mixed> */
    public array $metadata = [];
}
