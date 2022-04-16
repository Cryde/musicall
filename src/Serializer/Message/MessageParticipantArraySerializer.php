<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageParticipant;
use App\Serializer\User\UserArraySerializer;

class MessageParticipantArraySerializer
{
    private UserArraySerializer $userArraySerializer;

    public function __construct(UserArraySerializer $userArraySerializer)
    {
        $this->userArraySerializer = $userArraySerializer;
    }

    /**
     * @param MessageParticipant[] $participants
     */
    public function listToArray(Iterable $participants): array
    {
        $result = [];
        foreach ($participants as $participant) {
            $result[] = $this->toArray($participant);
        }

        return $result;
    }

    public function toArray(MessageParticipant $messageParticipant): array
    {
        return [
            'user' => $this->userArraySerializer->toArray($messageParticipant->getParticipant()),
        ];
    }
}
