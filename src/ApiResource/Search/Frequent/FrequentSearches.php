<?php

declare(strict_types=1);

namespace App\ApiResource\Search\Frequent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Search\FrequentSearchesProvider;

/**
 * « Recherches fréquentes » on the homepage (#1075): the searches enough different people ran lately.
 * One object rather than a collection, since its searches are not resources of their own.
 */
#[Get(
    uriTemplate: '/musicians/search/frequent',
    openapi: new Operation(tags: ['Musician announce']),
    name: 'api_musician_search_frequent',
    provider: FrequentSearchesProvider::class,
)]
class FrequentSearches
{
    /** @var list<FrequentSearch> */
    #[ApiProperty(genId: false)]
    public array $searches = [];
}
