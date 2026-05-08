<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Enum\BandSpace\BandSpaceModule;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BandSpaceActivity>
 */
final class BandSpaceActivityFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'module' => BandSpaceModule::File,
            'type' => 'uploaded',
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceActivity::class;
    }
}
