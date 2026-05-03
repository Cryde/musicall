<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AgendaEntry>
 */
final class AgendaEntryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'creator' => UserFactory::new(),
            'title' => self::faker()->sentence(3),
            'eventDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('+1 day', '+30 days')),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return AgendaEntry::class;
    }
}
