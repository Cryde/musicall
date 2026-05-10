<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Message\MessageThreadMeta;
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
    #[Groups([MessageThreadMeta::LIST])]
    public string $id;

    /** @var MessageParticipantResource[] */
    #[Groups([MessageThreadMeta::LIST])]
    public array $messageParticipants = [];

    #[Groups([MessageThreadMeta::LIST])]
    public ?MessageResource $lastMessage = null;
}
