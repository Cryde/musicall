<?php

namespace App\Service\Access;

use App\Entity\Message\MessageThread;
use App\Entity\User;

class ThreadAccess
{
    public function isOneOfParticipant(MessageThread $messageThread, User $user): bool
    {
        foreach ($messageThread->getMessageParticipants() as $participant) {
            if ($participant->getParticipant()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
