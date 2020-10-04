<?php

namespace App\Serializer\Message;

use App\Entity\Message\Message;
use App\Serializer\User\UserArraySerializer;
use App\Service\DatetimeHelper;

class LastMessageArraySerializer
{
    private \HTMLPurifier $messagePurifier;
    private UserArraySerializer $userArraySerializer;

    public function __construct(UserArraySerializer $userArraySerializer, \HTMLPurifier $lastMessagePurifier)
    {
        $this->messagePurifier = $lastMessagePurifier;
        $this->userArraySerializer = $userArraySerializer;
    }

    /**
     * @param Message[] $messages
     *
     * @return array
     */
    public function listToArray($messages): array
    {
        $result = [];
        foreach ($messages as $message) {
            $result[] = $this->toArray($message);
        }

        return $result;
    }

    public function toArray(Message $message): array
    {
        return [
            'id'                => $message->getId(),
            'author'            => $this->userArraySerializer->toArray($message->getAuthor()),
            'creation_datetime' => $message->getCreationDatetime()->format(DatetimeHelper::FORMAT_ISO_8601),
            'content'           => $this->messagePurifier->purify(nl2br($message->getContent())),
        ];
    }
}
