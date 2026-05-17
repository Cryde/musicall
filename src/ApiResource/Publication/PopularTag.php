<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Publication\PopularTagsProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'PopularTag',
    operations: [
        new GetCollection(
            uriTemplate: '/tags/popular',
            openapi: new Operation(tags: ['Tags']),
            paginationEnabled: false,
            name: 'api_tag_get_popular',
            provider: PopularTagsProvider::class,
            parameters: [
                'count' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    constraints: [new Assert\Range(min: 1, max: 50)],
                ),
            ],
        ),
    ],
)]
class PopularTag
{
    #[ApiProperty(identifier: true)]
    public string $slug;
    public string $label;
    public int $publicationCount = 0;
}
