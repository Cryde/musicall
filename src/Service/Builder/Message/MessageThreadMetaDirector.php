<?php

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;

class MessageThreadMetaDirector
{
    public function create(MessageThread $thread, User $user, bool $isRead): MessageThreadMeta
    {
        return (new MessageThreadMeta())
            ->setThread($thread)
            ->setIsDeleted(false)
            ->setIsRead($isRead)
            ->setUser($user);
    }
}
