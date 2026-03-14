<?php

namespace App\Tests\Unit\Service\Access;

use App\Entity\Message\MessageParticipant;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Service\Access\ThreadAccess;
use PHPUnit\Framework\TestCase;

class ThreadAccessTest extends TestCase
{
    public function testIsOneOfParticipant(): void
    {
        $user1 = (new User())->setId('user_id_1');
        $user2 = (new User())->setId('user_id_2');

        $participant = new MessageParticipant();
        $participant->participant = $user1;
        $messageThread = new MessageThread();
        $messageThread->addMessageParticipant($participant);

        $threadAccess = new ThreadAccess();

        $this->assertTrue($threadAccess->isOneOfParticipant($messageThread, $user1));
        $this->assertFalse($threadAccess->isOneOfParticipant($messageThread, $user2));
    }
}
