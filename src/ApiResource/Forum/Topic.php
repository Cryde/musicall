<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\Forum;
use App\Entity\Forum\ForumTopic;
use App\State\Provider\Forum\TopicProvider;

#[Get(
    uriTemplate: '/forums/topics/{slug}',
    uriVariables: ['slug'],
    openapi: new Operation(tags: ['Forum']),
    name: 'api_forum_topic_get',
    provider: TopicProvider::class,
)]
class Topic
{
    #[ApiProperty(identifier: false)]
    public string $id;

    public string $title;

    #[ApiProperty(identifier: true)]
    public string $slug;

    #[ApiProperty(genId: false)]
    public Forum $forum;
}
