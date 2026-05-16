<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\Forum\Data\ForumTopicSummary;
use App\State\Processor\Forum\ForumTopicParticipationRemoveProcessor;
use App\State\Processor\Forum\ForumTopicParticipationRestoreProcessor;
use App\State\Provider\Forum\ForumTopicParticipationCollectionProvider;
use App\State\Provider\Forum\ForumTopicParticipationItemProvider;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'ForumTopicParticipation',
    operations: [
        new GetCollection(
            uriTemplate: '/forums/topic-participations',
            openapi: new Operation(tags: ['Forum']),
            paginationEnabled: true,
            paginationItemsPerPage: 15,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_participations_list',
            provider: ForumTopicParticipationCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/forums/topic-participations/{id}/remove',
            uriVariables: ['id'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_participation_remove',
            provider: ForumTopicParticipationItemProvider::class,
            processor: ForumTopicParticipationRemoveProcessor::class,
        ),
        new Post(
            uriTemplate: '/forums/topic-participations/{id}/restore',
            uriVariables: ['id'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Forum']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_forum_topic_participation_restore',
            provider: ForumTopicParticipationItemProvider::class,
            processor: ForumTopicParticipationRestoreProcessor::class,
        ),
    ]
)]
class ForumTopicParticipationResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public bool $isRead;
    public DateTimeInterface $creationDatetime;

    #[ApiProperty(genId: false)]
    public ForumTopicSummary $topic;
}
