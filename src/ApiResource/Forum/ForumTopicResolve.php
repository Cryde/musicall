<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Forum\ForumTopicResolveProcessor;
use App\State\Processor\Forum\ForumTopicUnresolveProcessor;
use App\State\Provider\Forum\ForumTopicSlugActionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/forums/topics/{slug}/resolve',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_resolve',
            provider: ForumTopicSlugActionProvider::class,
            processor: ForumTopicResolveProcessor::class,
        ),
        new Post(
            uriTemplate: '/forums/topics/{slug}/unresolve',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_unresolve',
            provider: ForumTopicSlugActionProvider::class,
            processor: ForumTopicUnresolveProcessor::class,
        ),
    ]
)]
class ForumTopicResolve
{
    #[ApiProperty(identifier: true)]
    public string $slug;
}
