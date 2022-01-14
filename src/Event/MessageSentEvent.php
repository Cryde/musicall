<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class MessageSentEvent extends Event
{
    public const NAME = 'message.sent';
    private User $recipient;

    public function __construct(User $recipient)
    {
        $this->recipient = $recipient;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }
}
