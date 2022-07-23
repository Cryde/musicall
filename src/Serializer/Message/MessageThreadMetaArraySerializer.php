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

    /**
     * @param MessageThreadMeta[] $messageThreadMetas
     */
    public function listToArray(iterable $messageThreadMetas, bool $withThread = false): array
    {
        $result = [];
        foreach ($messageThreadMetas as $messageThreadMeta) {
            if ($withThread) {
                $thread = $messageThreadMeta->getThread();
                $result[] = [
                    'thread'       => $this->messageThreadArraySerializer->toArray($thread),
                    'meta'         => $this->toArray($messageThreadMeta),
                    'participants' => $this->messageParticipantArraySerializer->listToArray($thread->getMessageParticipants()),
                ];
            } else {
                $result[] = $this->toArray($messageThreadMeta);
            }
        }

        return $result;
    }

    public function toArray(MessageThreadMeta $messageThreadMeta): array
    {
        return [
            'is_read' => $messageThreadMeta->getIsRead(),
        ];
    }
}
