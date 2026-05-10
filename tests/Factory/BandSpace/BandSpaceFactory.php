<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpace;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpace>
 */
final class BandSpaceFactory extends PersistentObjectFactory
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
