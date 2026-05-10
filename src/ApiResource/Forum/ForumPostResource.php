<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\User;
use App\Entity\Forum\ForumTopic;
use App\State\Provider\Forum\ForumPostCollectionProvider;
use App\State\Provider\Forum\ForumPostItemProvider;
use DateTimeInterface;

#[ApiResource(
    shortName: 'TopicPost',
    operations: [
        new GetCollection(
            uriTemplate: '/forums/topics/{slug}/posts',
            uriVariables: [
                'slug' => new Link(fromClass: ForumTopic::class, identifiers: ['slug']),
            ],
            openapi: new Operation(tags: ['Forum']),
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            name: 'api_forum_topic_posts_list',
            provider: ForumPostCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/topic_posts/{id}',
            openapi: new Operation(tags: ['Forum']),
            name: 'api_forum_topic_posts_get',
            provider: ForumPostItemProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ForumPostResource
{
    #[ApiProperty(identifier: true)]
    public string $id;
    public DateTimeInterface $creationDatetime;
    public ?DateTimeInterface $updateDatetime = null;
    public string $content;

    #[ApiProperty(genId: false)]
    public User $creator;
    public int $upvotes = 0;
    public int $downvotes = 0;
    public ?int $userVote = null;
}
