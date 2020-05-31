<?php

namespace App\Service\Builder\Message;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;

class MessageDirector
{
    public function create(MessageThread $thread, User $author, string $content) : Message
    {
        return (new Message())
            ->setContent($content)
            ->setAuthor($author)
            ->setThread($thread);
    }
}
