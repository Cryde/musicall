<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Message\MessageThreadItemProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only DTO for MessageThread. The Get item operation exists primarily so
 * the API Platform IRI converter can resolve `/api/message_threads/{id}`
 * strings to a populated `MessageThreadResource` during input denormalization
 * (e.g. `MessageCreation->thread`). Direct HTTP access only renders `@id`,
 * `@type`, `id` (auth required) — full participants / last_message do not
 * leak through this endpoint.
 */
#[ApiResource(
    shortName: 'MessageThread',
    operations: [
        new Get(
            uriTemplate: '/message_threads/{id}',
            openapi: new Operation(tags: ['Message']),
            normalizationContext: ['groups' => [MessageResource::ITEM]],
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            name: 'api_message_thread_get_item',
            provider: MessageThreadItemProvider::class,
        ),
    ],
)]
class MessageThreadResource
{
    #[ApiProperty(identifier: true)]
    #[Groups([MessageThreadMetaResource::LIST, MessageResource::ITEM])]
    public string $id;

    /** @var MessageParticipantResource[] */
    #[Groups([MessageThreadMetaResource::LIST])]
    public array $messageParticipants = [];

    #[Groups([MessageThreadMetaResource::LIST])]
    public ?MessageResource $lastMessage = null;
}
