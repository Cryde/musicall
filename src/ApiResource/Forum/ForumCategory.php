<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\Forum;
use App\State\Provider\Forum\ForumCategoryProvider;

#[GetCollection(
    uriTemplate: '/forums/categories',
    openapi: new Operation(tags: ['Forum']),
    paginationEnabled: false,
    name: 'api_forum_categories_list',
    provider: ForumCategoryProvider::class,
)]
class ForumCategory
{
    public string $id;
    public string $title;

    /** @var Forum[] */
    #[ApiProperty(genId: false)]
    public array $forums = [];
}
