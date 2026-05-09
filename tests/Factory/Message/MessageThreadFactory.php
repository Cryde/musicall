<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageThread;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class MessageThreadFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return MessageThread::class;
    }
}
