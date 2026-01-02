<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\ForumPost;
use App\ApiResource\Forum\Data\User;
use App\Entity\Forum\Forum;
use App\State\Provider\Forum\ForumTopicListProvider;
use DateTimeInterface;

#[GetCollection(
    uriTemplate: '/forums/{slug}/topics',
    uriVariables: [
        'slug' => new Link(fromClass: Forum::class, identifiers: ['slug']),
    ],
    openapi: new Operation(tags: ['Forum']),
    paginationEnabled: true,
    paginationItemsPerPage: 15,
    normalizationContext: ['skip_null_values' => false],
    name: 'api_forum_topics_list',
    provider: ForumTopicListProvider::class,
)]
class ForumTopic
{
    #[ApiProperty(identifier: false)]
    public string $id;

    public string $title;

    #[ApiProperty(identifier: true)]
    public string $slug;

    public int $type;
    public bool $isLocked;

    #[ApiProperty(genId: false)]
    public ?ForumPost $lastPost = null;

    public DateTimeInterface $creationDatetime;

    #[ApiProperty(genId: false)]
    public User $author;

    public int $postNumber;
}
