<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Publication;
use App\State\Provider\Publication\LastPublicationsProvider;

#[GetCollection(
    shortName: 'Publication',
    uriTemplate: '/last-publications',
    openapi: new Operation(tags: ['Publications']),
    paginationEnabled: false,
    priority: 1,
    normalizationContext: ['groups' => [Publication::LIST], 'skip_null_values' => false],
    name: 'api_publication_get_last',
    provider: LastPublicationsProvider::class,
)]
class LastPublication
{
}
