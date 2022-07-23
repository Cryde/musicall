<?php

namespace App\Serializer\Message;

use App\Entity\Message\Message;
use App\Serializer\User\UserArraySerializer;
use App\Service\DatetimeHelper;
use HtmlSanitizer\SanitizerInterface;

class LastMessageArraySerializer
{
    public function __construct(private readonly UserArraySerializer $userArraySerializer, private readonly SanitizerInterface $sanitizer)
    {
    }

    /**
     * @param Message[] $messages
     */
    public function listToArray(iterable $messages): array
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
            'content'           => $this->sanitizer->sanitize(nl2br($message->getContent())),
        ];
    }
}
