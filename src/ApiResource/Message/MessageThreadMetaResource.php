<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Message\MessageThreadMetaPatchProcessor;
use App\State\Provider\Message\MessageThreadMetaCollectionProvider;
use App\State\Provider\Message\MessageThreadMetaItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'MessageThreadMeta',
    operations: [
        new GetCollection(
            uriTemplate: '/message_thread_metas',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMetaResource::LIST]],
            name: 'api_message_thread_meta_get_collection',
            provider: MessageThreadMetaCollectionProvider::class,
        ),
        new Patch(
            uriTemplate: '/message_thread_metas/{id}',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMetaResource::ITEM]],
            denormalizationContext: ['groups' => [MessageThreadMetaResource::PATCH]],
            name: 'api_message_thread_meta_patch',
            provider: MessageThreadMetaItemProvider::class,
            processor: MessageThreadMetaPatchProcessor::class,
        ),
    ],
)]
class MessageThreadMetaResource
{
    public const string LIST = 'message_thread_meta:list';
    public const string ITEM = 'message_thread_meta:item';
    public const string PATCH = 'message_thread_meta:patch';

    #[ApiProperty(identifier: true)]
    #[Groups([MessageThreadMetaResource::LIST, MessageThreadMetaResource::ITEM])]
    public string $id;

    #[Groups([MessageThreadMetaResource::LIST, MessageThreadMetaResource::PATCH])]
    public bool $isRead;

    #[Groups([MessageThreadMetaResource::LIST])]
    public MessageThreadResource $thread;
}
