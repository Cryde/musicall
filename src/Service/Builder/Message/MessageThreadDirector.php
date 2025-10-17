<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\MessageThread;

class MessageThreadDirector
{
    public function create(): MessageThread
    {
        return new MessageThread();
    }
}
