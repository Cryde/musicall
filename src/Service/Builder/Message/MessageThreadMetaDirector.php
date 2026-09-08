<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;

class MessageThreadMetaDirector
{
    /**
     * @param \DateTimeImmutable|null $lastReadDatetime how far this user has already read, null when
     *                                                 they have read nothing. The sender of the first
     *                                                 message has read it; the recipient has not.
     */
    public function create(MessageThread $thread, User $user, ?\DateTimeImmutable $lastReadDatetime): MessageThreadMeta
    {
        $meta = new MessageThreadMeta();
        $meta->thread = $thread;
        $meta->isDeleted = false;
        $meta->lastReadDatetime = $lastReadDatetime;
        $meta->user = $user;

        return $meta;
    }
}
