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
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\State\Processor\Message\MessagePostProcessor;
use App\State\Provider\Message\MessageCollectionProvider;
use App\Validator\Message\NotDeletedThreadRecipient;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[NotDeletedThreadRecipient]
#[ApiResource(
    shortName: 'Message',
    operations: [
        new GetCollection(
            uriTemplate: '/messages/{threadId}',
            uriVariables: [
                'threadId' => new Link(toProperty: 'thread', fromClass: MessageThread::class),
            ],
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageResource::LIST]],
            name: 'api_message_get_collection',
            provider: MessageCollectionProvider::class,
            stateOptions: new Options(entityClass: Message::class),
        ),
        new Post(
            uriTemplate: '/messages',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageResource::ITEM]],
            denormalizationContext: ['groups' => [MessageResource::POST]],
            name: 'api_message_post',
            processor: MessagePostProcessor::class,
        ),
    ],
)]
#[ApiFilter(OrderFilter::class, properties: ['creationDatetime' => OrderFilterInterface::DIRECTION_DESC])]
class MessageResource
{
    public const string LIST = 'message:list';
    public const string ITEM = 'message:item';
    public const string POST = 'message:post';

    #[ApiProperty(identifier: true)]
    #[Groups([MessageResource::ITEM])]
    public string $id;

    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageThreadMeta::LIST])]
    public DateTimeInterface $creationDatetime;

    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageThreadMeta::LIST])]
    public User $author;

    #[Assert\NotNull]
    #[Groups([MessageResource::ITEM, MessageResource::POST])]
    public MessageThread $thread;

    #[Assert\NotBlank]
    #[Groups([MessageResource::LIST, MessageResource::ITEM, MessageResource::POST, MessageThreadMeta::LIST])]
    public string $content;
}
