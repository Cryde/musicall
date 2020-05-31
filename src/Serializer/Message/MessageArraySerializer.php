<?php

namespace App\Serializer\Message;

use App\Entity\Message\Message;
use App\Serializer\UserAppArraySerializer;
use App\Service\DatetimeHelper;

class MessageArraySerializer
{
    /**
     * @var UserAppArraySerializer
     */
    private UserAppArraySerializer $userAppArraySerializer;
    private \HTMLPurifier $messagePurifier;

    public function __construct(UserAppArraySerializer $userAppArraySerializer, \HTMLPurifier $messagePurifier)
    {
        $this->userAppArraySerializer = $userAppArraySerializer;
        $this->messagePurifier = $messagePurifier;
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
            'author'            => $this->userAppArraySerializer->toArray($message->getAuthor()),
            'creation_datetime' => $message->getCreationDatetime()->format(DatetimeHelper::FORMAT_ISO_8601),
            'content'           => $this->messagePurifier->purify(nl2br($message->getContent())),
        ];
    }
}
