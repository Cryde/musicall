<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Publication\PublicationListItem\Author;
use App\ApiResource\Publication\PublicationListItem\SubCategory;
use App\State\Provider\Publication\PublicationCollectionProvider;
use DateTimeInterface;

#[ApiResource(
    shortName: 'Publication',
    operations: [
        new GetCollection(
            uriTemplate: '/publications',
            openapi: new Operation(tags: ['Publications']),
            paginationItemsPerPage: 12,
            normalizationContext: ['skip_null_values' => false],
            name: 'api_publication_get_collection',
            provider: PublicationCollectionProvider::class,
            parameters: [
                'sub_category.slug' => new QueryParameter(
                    key: 'sub_category.slug',
                    schema: ['type' => 'string'],
                    property: 'sub_category.slug',
                    description: 'Filter by sub-category slug',
                    required: false,
                ),
                'sub_category.type' => new QueryParameter(
                    key: 'sub_category.type',
                    schema: ['type' => 'integer'],
                    property: 'sub_category.type',
                    description: 'Filter by sub-category type',
                    required: false,
                ),
                'order[publication_datetime]' => new QueryParameter(
                    key: 'order[publication_datetime]',
                    schema: ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                    property: 'publication_datetime',
                    description: 'Order by publication datetime',
                    required: false,
                ),
                'page' => new QueryParameter(
                    key: 'page',
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    property: 'page',
                    description: 'Page number for pagination',
                ),
            ],
        ),
    ],
)]
class PublicationListItem
{
    #[ApiProperty(identifier: false)]
    public int $id;

    public string $title;

    #[ApiProperty(genId: false)]
    public SubCategory $subCategory;

    #[ApiProperty(genId: false)]
    public Author $author;

    #[ApiProperty(identifier: true)]
    public string $slug;

    public DateTimeInterface $publicationDatetime;
    public ?string $cover = null;
    public string $typeLabel;
    public ?string $description = null;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
