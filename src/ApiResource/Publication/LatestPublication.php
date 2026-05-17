<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Publication;
use App\State\Provider\Publication\LatestPublicationsProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[GetCollection(
    shortName: 'Publication',
    uriTemplate: '/publications/latest',
    openapi: new Operation(tags: ['Publications']),
    paginationEnabled: false,
    priority: 2,
    normalizationContext: ['groups' => [Publication::LIST], 'skip_null_values' => false],
    name: 'api_publication_get_latest',
    provider: LatestPublicationsProvider::class,
    parameters: [
        'excludeId' => new QueryParameter(
            schema: ['type' => 'integer'],
            constraints: [new Assert\Positive()],
        ),
        'count' => new QueryParameter(
            schema: ['type' => 'integer'],
            constraints: [new Assert\Range(min: 1, max: 20)],
        ),
        // 1 = PublicationSubCategory::TYPE_PUBLICATION, 2 = TYPE_COURSE
        'subCategoryType' => new QueryParameter(
            schema: ['type' => 'integer'],
            constraints: [new Assert\Range(min: 1, max: 2)],
        ),
    ],
)]
class LatestPublication
{
}
