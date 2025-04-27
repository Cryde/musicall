<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\Message;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class MessageFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'content' => self::faker()->text(),
            'creationDatetime' => new \DateTime(),
            'thread' => MessageThreadFactory::new(),
        ];
    }

    public static function class(): string
    {
        return Message::class;
    }
}
