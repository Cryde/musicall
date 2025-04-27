<?php

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageParticipant;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class MessageParticipantFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'participant' => UserFactory::new(),
            'thread' => MessageThreadFactory::new(),
        ];
    }

    public static function class(): string
    {
        return MessageParticipant::class;
    }
}
