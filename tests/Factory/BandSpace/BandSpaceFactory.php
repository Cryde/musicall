<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpace;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BandSpace>
 */
final class BandSpaceFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpace::class;
    }
}
