<?php declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;

class MessageThreadDirector
{
    public function create(): MessageThread
    {
        return new MessageThread();
    }

    /**
     * No participant rows: a channel's members are derived from BandSpaceMembership. The name is
     * required here because the unique index on (band_space_id, name) cannot enforce it.
     */
    public function createForBandSpace(BandSpace $bandSpace, string $name = MessageThread::DEFAULT_CHANNEL_NAME): MessageThread
    {
        $thread = new MessageThread();
        $thread->bandSpace = $bandSpace;
        $thread->name = $name;

        return $thread;
    }
}
