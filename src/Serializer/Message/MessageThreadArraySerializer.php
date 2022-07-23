<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageThread;

class MessageThreadArraySerializer
{
    public function __construct(private readonly LastMessageArraySerializer $lastMessageArraySerializer)
    {
    }

    public function toArray(MessageThread $messageThread): array
    {
        return [
            'id'           => $messageThread->getId(),
            'last_message' => $this->lastMessageArraySerializer->toArray($messageThread->getLastMessage()),
        ];
    }
}
