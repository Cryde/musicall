<?php declare(strict_types=1);

namespace App\Service\Builder\Message;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;

class MessageDirector
{
    public function create(MessageThread $thread, User $author, string $content) : Message
    {
        $message = new Message();
        $message->content = $content;
        $message->author = $author;
        $message->thread = $thread;

        return $message;
    }
}
