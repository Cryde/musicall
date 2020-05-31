<?php

namespace App\Serializer\Message;

use App\Entity\Message\MessageParticipant;
use App\Serializer\UserAppArraySerializer;

class MessageParticipantArraySerializer
{
    /**
     * @var UserAppArraySerializer
     */
    private UserAppArraySerializer $userAppArraySerializer;

    public function __construct(UserAppArraySerializer $userAppArraySerializer)
    {
        $this->userAppArraySerializer = $userAppArraySerializer;
    }

    /**
     * @param MessageParticipant[] $participants
     *
     * @return array
     */
    public function listToArray($participants): array
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
            'user' => $this->userAppArraySerializer->toArray($messageParticipant->getParticipant()),
        ];
    }
}
