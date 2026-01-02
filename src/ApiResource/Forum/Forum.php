<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\ForumCategory;
use App\Entity\Forum\Forum as ForumEntity;
use App\State\Provider\Forum\ForumProvider;

#[Get(
    uriTemplate: '/forum/{slug}',
    uriVariables: [
        'slug' => new Link(fromClass: ForumEntity::class, identifiers: ['slug']),
    ],
    openapi: new Operation(tags: ['Forum']),
    name: 'api_forum_detail',
    provider: ForumProvider::class,
)]
class Forum
{
    public string $id;
    public string $title;

    #[ApiProperty(genId: false)]
    public ForumCategory $forumCategory;
}
