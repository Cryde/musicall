<?php declare(strict_types=1);

namespace App\Service\Access;

use App\Entity\Message\MessageThread;
use App\Entity\User;

class ThreadAccess
{
    public function isOneOfParticipant(MessageThread $messageThread, User $user): bool
    {
        foreach ($messageThread->messageParticipants as $participant) {
            if ($participant->participant->id === $user->id) {
                return true;
            }
        }

        return false;
    }
}
