<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageParticipant;
use App\Entity\Message\MessageThread;
use App\Entity\User;

class MessageParticipantDirector
{
    public function create(MessageThread $thread, User $participant): MessageParticipant
    {
        $messageParticipant = new MessageParticipant();
        $messageParticipant->participant = $participant;
        // Through the thread, so the in-memory collection matches what was persisted. Setting only
        // `$messageParticipant->thread` is enough for Doctrine, and leaves anything that reads
        // `$thread->messageParticipants` later in the same request seeing an empty thread.
        $thread->addMessageParticipant($messageParticipant);

        return $messageParticipant;
    }
}
