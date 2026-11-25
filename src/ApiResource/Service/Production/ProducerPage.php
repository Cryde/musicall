<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Production;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Service\ServicePage;
use App\State\Provider\Service\ProducerPageProvider;

/** A producer's page (#1413), `/u/{username}/producer`: what every kind's page shows, their works as credits. */
#[Get(
    uriTemplate: '/user/profile/{username}/producer',
    requirements: ['username' => '[^/]+'],
    openapi: new Operation(tags: ['Service']),
    normalizationContext: ['skip_null_values' => false],
    name: 'api_producer_page_get',
    provider: ProducerPageProvider::class,
)]
class ProducerPage extends ServicePage
{
}
