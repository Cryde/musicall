<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceBootstrapProcessor;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/bootstrap',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: FinanceCategoryResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    status: 201,
    name: 'api_band_space_finance_bootstrap',
    processor: FinanceBootstrapProcessor::class,
)]
class FinanceBootstrapRequest
{
}
