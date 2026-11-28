<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Search;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Service\ServiceKindCountProvider;

/** How many services each kind's search lists without a city, for the « Annuaire » (#1414): every kind, zeros included. */
#[GetCollection(
    uriTemplate: '/services/kinds',
    openapi: new Operation(tags: ['Service']),
    paginationEnabled: false,
    name: 'api_services_kinds',
    provider: ServiceKindCountProvider::class,
)]
#[NotExposed(uriTemplate: '/services/kinds/{kind}')]
class ServiceKindCount
{
    /** A ServiceKind value. */
    #[ApiProperty(identifier: true)]
    public string $kind;

    public int $count;
}
