<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\State\Processor\Message\MessagePostProcessor;
use App\State\Provider\Message\MessageCollectionProvider;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Message',
    operations: [
        new GetCollection(
            uriTemplate: '/messages/{threadId}',
            uriVariables: [
                'threadId' => new Link(toProperty: 'thread', fromClass: MessageThread::class),
            ],
            openapi: new Operation(tags: ['Message']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationClientItemsPerPage: true,
            // The client may choose a page size, so it needs a ceiling: without one a thread can be
            // asked for in a single unbounded page. Same cap as BandSpaceActivityResource.
            paginationMaximumItemsPerPage: 200,
            normalizationContext: ['groups' => [MessageResource::LIST]],
            name: 'api_message_get_collection',
            provider: MessageCollectionProvider::class,
            stateOptions: new Options(entityClass: Message::class),
        ),
        new Post(
            uriTemplate: '/messages',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageResource::ITEM]],
            input: MessageCreation::class,
            name: 'api_message_post',
            processor: MessagePostProcessor::class,
        ),
    ],
)]
/**
 * `id` is in the sort for a tiebreak, not because anybody wants to order by a uuid4.
 *
 * `creation_datetime` is second granular and same second pairs are ordinary rather than rare, and SQL
 * promises nothing about the order of tied rows. Two page requests that happened to be planned
 * differently could then put a tied row on both sides of a page boundary, or on neither, and the
 * second of those loses a message. Naming `id` makes the total order explicit instead of borrowing it
 * from whichever index the planner picked, which is what the composite index from #955 happens to
 * give today. The client has to ask for it: OrderFilter only orders by what the request names.
 */
#[ApiFilter(OrderFilter::class, properties: [
    'creationDatetime' => OrderFilterInterface::DIRECTION_DESC,
    'id' => OrderFilterInterface::DIRECTION_DESC,
])]
class MessageResource
{
    public const string LIST = 'message:list';
    public const string ITEM = 'message:item';

    #[ApiProperty(identifier: true)]
    #[Groups([MessageResource::ITEM])]
    public string $id;

    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageThreadMetaResource::LIST])]
    public DateTimeInterface $creationDatetime;

    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageThreadMetaResource::LIST])]
    public User $author;

    #[Groups([MessageResource::ITEM])]
    public MessageThreadResource $thread;

    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageThreadMetaResource::LIST])]
    public string $content; // HTML, render with v-html

    #[Groups([MessageResource::ITEM, MessageThreadMetaResource::LIST])]
    public string $contentPreview; // plain text, never v-html
}
