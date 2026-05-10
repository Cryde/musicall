<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;

/**
 * Read-only DTO for MessageParticipant. No HTTP operations are exposed — the entity
 * never had a real consumer for its Get route, only IRI generation in nested
 * `MessageThread.message_participants` rendering. Empty `operations: []` keeps the
 * resource registered for IRI / @type metadata once #667 strips the entity-side
 * `#[ApiResource]` and the rendering pipeline switches to this DTO.
 */
#[ApiResource(
    shortName: 'MessageParticipant',
    operations: [],
)]
class MessageParticipantResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public User $participant;
}
