<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Publication\PublicationCategoryProvider;

#[GetCollection(
    uriTemplate: '/publication-categories',
    openapi: new Operation(tags: ['Publications']),
    paginationEnabled: false,
    name: 'api_publication_categories_list',
    provider: PublicationCategoryProvider::class,
)]
class PublicationCategory
{
    public int $id;
    public string $title;
    public string $slug;
    public ?int $position;
}
