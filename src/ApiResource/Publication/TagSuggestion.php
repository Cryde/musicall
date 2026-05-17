<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Publication\TagSuggestionCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[GetCollection(
    uriTemplate: '/tags',
    openapi: new Operation(tags: ['Tags']),
    paginationEnabled: false,
    name: 'api_tags_list',
    provider: TagSuggestionCollectionProvider::class,
    parameters: [
        'label' => new QueryParameter(
            key: 'label',
            description: 'Prefix match on tag label (case-insensitive)',
            required: false,
            constraints: [
                new Assert\Length(max: 100),
            ],
        ),
    ],
)]
class TagSuggestion
{
    #[ApiProperty(identifier: true)]
    public string $slug;

    public string $label;
}
