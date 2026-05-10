<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Message\MessageThreadMeta;
use App\State\Provider\Message\MessageThreadMetaCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'MessageThreadMeta',
    operations: [
        new GetCollection(
            uriTemplate: '/message_thread_metas',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageThreadMeta::LIST]],
            name: 'api_message_thread_meta_get_collection',
            provider: MessageThreadMetaCollectionProvider::class,
        ),
    ],
)]
class MessageThreadMetaResource
{
    #[ApiProperty(identifier: true)]
    #[Groups([MessageThreadMeta::LIST])]
    public string $id;

    #[Groups([MessageThreadMeta::LIST])]
    public bool $isRead;

    #[Groups([MessageThreadMeta::LIST])]
    public MessageThreadResource $thread;
}
