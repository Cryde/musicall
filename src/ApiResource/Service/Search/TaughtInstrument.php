<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Service\TaughtInstrumentCollectionProvider;

/** What the teacher search's instrument filter offers (#1314): only the instruments a listed teacher teaches. */
#[GetCollection(
    uriTemplate: '/services/teaching/instruments',
    openapi: new Operation(tags: ['Service']),
    paginationEnabled: false,
    name: 'api_services_teaching_instruments',
    provider: TaughtInstrumentCollectionProvider::class,
)]
#[NotExposed(uriTemplate: '/services/teaching/instruments/{id}')]
class TaughtInstrument
{
    #[ApiProperty(identifier: true)]
    public string $id;
    public string $name;
    /** What the search page's address holds. */
    public string $slug;
}
