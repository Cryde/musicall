<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;

/**
 * Read-only DTO for MessageThread. The entity-level Get only exists to populate
 * `@id`/`@type` on nested `thread` objects in `MessageThreadMetaResource`. This DTO
 * registers the same metadata via `operations: []` without exposing an HTTP route —
 * #667 wires it into `MessageThreadMetaResource` and strips the entity-side
 * `#[ApiResource]` / `#[Groups]`.
 */
#[ApiResource(
    shortName: 'MessageThread',
    operations: [],
)]
class MessageThreadResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /** @var MessageParticipantResource[] */
    public array $messageParticipants = [];

    public ?MessageResource $lastMessage = null;
}
