<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageThreadMeta;

class MessageThreadMetaArraySerializer
{
    private MessageThreadArraySerializer $messageThreadArraySerializer;
    private MessageParticipantArraySerializer $messageParticipantArraySerializer;

    public function __construct(
        MessageThreadArraySerializer $messageThreadArraySerializer,
        MessageParticipantArraySerializer $messageParticipantArraySerializer
    ) {
        $this->messageThreadArraySerializer = $messageThreadArraySerializer;
        $this->messageParticipantArraySerializer = $messageParticipantArraySerializer;
    }

    /**
     * @param MessageThreadMeta[] $messageThreadMetas
     * @param bool                $withThread
     *
     * @return array
     */
    public function listToArray($messageThreadMetas, bool $withThread = false): array
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
