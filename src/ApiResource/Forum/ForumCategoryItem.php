<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Forum\ForumSource;
use App\State\Provider\Forum\ForumCategoryListProvider;

#[GetCollection(
    uriTemplate: '/forums/categories',
    openapi: new Operation(tags: ['Forum']),
    paginationEnabled: false,
    name: 'api_forum_categories_list',
    provider: ForumCategoryListProvider::class,
)]
class ForumCategoryItem
{
    public string $id;
    public string $title;

    /** @var ForumItem[] */
    #[ApiProperty(genId: false)]
    public array $forums = [];
}
