<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Forum\ForumTopicPinProcessor;
use App\State\Processor\Forum\ForumTopicUnpinProcessor;
use App\State\Provider\Forum\ForumTopicSlugActionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/forums/topics/{slug}/pin',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_forum_topic_pin',
            provider: ForumTopicSlugActionProvider::class,
            processor: ForumTopicPinProcessor::class,
        ),
        new Post(
            uriTemplate: '/forums/topics/{slug}/unpin',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_forum_topic_unpin',
            provider: ForumTopicSlugActionProvider::class,
            processor: ForumTopicUnpinProcessor::class,
        ),
    ]
)]
class ForumTopicPin
{
    #[ApiProperty(identifier: true)]
    public string $slug;
}
