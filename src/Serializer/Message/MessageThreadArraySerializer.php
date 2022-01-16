<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageThread;

class MessageThreadArraySerializer
{
    private LastMessageArraySerializer $lastMessageArraySerializer;

    public function __construct(LastMessageArraySerializer $lastMessageArraySerializer)
    {
        $this->lastMessageArraySerializer = $lastMessageArraySerializer;
    }

    public function toArray(MessageThread $messageThread): array
    {
        return [
            'id'           => $messageThread->getId(),
            'last_message' => $this->lastMessageArraySerializer->toArray($messageThread->getLastMessage()),
        ];
    }
}
