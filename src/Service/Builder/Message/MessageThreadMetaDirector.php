<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;

class MessageThreadMetaDirector
{
    public function create(MessageThread $thread, User $user, bool $isRead): MessageThreadMeta
    {
        $meta = new MessageThreadMeta();
        $meta->thread = $thread;
        $meta->isDeleted = false;
        $meta->isRead = $isRead;
        $meta->user = $user;

        return $meta;
    }
}
