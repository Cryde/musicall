<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only DTO for MessageParticipant. Metadata-only registration: nested rendering
 * inside `MessageThreadResource.message_participants` resolves @id / @type through
 * here.
 */
#[ApiResource(
    shortName: 'MessageParticipant',
    operations: [],
)]
class MessageParticipantResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[Groups([MessageThreadMetaResource::LIST])]
    public User $participant;
}
