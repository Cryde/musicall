<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageThreadMeta;

class MessageThreadMetaArraySerializer
{
    public function toArray(MessageThreadMeta $messageThreadMeta): array
    {
        return [
            'is_read' => $messageThreadMeta->getIsRead(),
        ];
    }
}
