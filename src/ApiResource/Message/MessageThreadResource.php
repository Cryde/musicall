<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only DTO for MessageThread. Only registered as a resource (no HTTP routes)
 * so the IRI / @type metadata flows through to nested rendering inside
 * `MessageThreadMetaResource.thread`.
 */
#[ApiResource(
    shortName: 'MessageThread',
    operations: [],
)]
class MessageThreadResource
{
    #[ApiProperty(identifier: true)]
    #[Groups([MessageThreadMetaResource::LIST])]
    public string $id;

    /** @var MessageParticipantResource[] */
    #[Groups([MessageThreadMetaResource::LIST])]
    public array $messageParticipants = [];

    #[Groups([MessageThreadMetaResource::LIST])]
    public ?MessageResource $lastMessage = null;
}
