<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageThread;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class MessageThreadFactory extends PersistentProxyObjectFactory
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
