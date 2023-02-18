<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageThreadMeta;

class MessageThreadMetaArraySerializer
{
    public function __construct(
        private readonly MessageThreadArraySerializer      $messageThreadArraySerializer,
        private readonly MessageParticipantArraySerializer $messageParticipantArraySerializer
    ) {
    }



    public function toArray(MessageThreadMeta $messageThreadMeta): array
    {
        return [
            'is_read' => $messageThreadMeta->getIsRead(),
        ];
    }
}
