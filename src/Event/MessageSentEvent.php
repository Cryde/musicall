<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class MessageSentEvent extends Event
{
    final public const NAME = 'message.sent';

    public function __construct(private readonly User $recipient)
    {
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }
}
