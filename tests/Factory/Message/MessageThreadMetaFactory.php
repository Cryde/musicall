<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageThreadMeta;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class MessageThreadMetaFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'isDeleted' => false,
            'isRead' => false,
            'thread' => MessageThreadFactory::new(),
            'user' => UserFactory::new(),
        ];
    }

    public static function class(): string
    {
        return MessageThreadMeta::class;
    }
}
