<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Publication\RelatedPublicationProvider;

#[GetCollection(
    shortName: 'Publication',
    uriTemplate: '/publications/{slug}/related',
    openapi: new Operation(tags: ['Publications']),
    paginationEnabled: false,
    normalizationContext: ['skip_null_values' => false],
    output: PublicationListItem::class,
    name: 'api_publication_related',
    provider: RelatedPublicationProvider::class,
)]
class RelatedPublication
{
}
