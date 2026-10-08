<?php

declare(strict_types=1);

namespace App\Tests\Factory\Message;

use App\Entity\Message\MessageContactOrigin;
use App\Entity\Musician\MusicianAnnounce;
use App\Enum\Message\ContactOriginType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class MessageContactOriginFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'message' => MessageFactory::new(),
            'type' => ContactOriginType::MusicianAnnounce,
            'announceType' => MusicianAnnounce::TYPE_MUSICIAN,
            'instruments' => ['Batteur'],
            'styles' => ['Rock'],
            'locationName' => 'Lyon',
        ];
    }

    public static function class(): string
    {
        return MessageContactOrigin::class;
    }
}
