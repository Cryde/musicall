<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Forum\ForumTopicLockProcessor;
use App\State\Processor\Forum\ForumTopicUnlockProcessor;
use App\State\Provider\Forum\ForumTopicLockActionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/forums/topics/{slug}/lock',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_lock',
            provider: ForumTopicLockActionProvider::class,
            processor: ForumTopicLockProcessor::class,
        ),
        new Post(
            uriTemplate: '/forums/topics/{slug}/unlock',
            uriVariables: ['slug'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_unlock',
            provider: ForumTopicLockActionProvider::class,
            processor: ForumTopicUnlockProcessor::class,
        ),
    ]
)]
class ForumTopicLock
{
    #[ApiProperty(identifier: true)]
    public string $slug;
}
