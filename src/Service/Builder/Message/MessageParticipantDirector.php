<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageParticipant;
use App\Entity\Message\MessageThread;
use App\Entity\User;

class MessageParticipantDirector
{
    public function create(MessageThread $thread, User $participant): MessageParticipant
    {
        return (new MessageParticipant())
            ->setThread($thread)
            ->setParticipant($participant);
    }
}
