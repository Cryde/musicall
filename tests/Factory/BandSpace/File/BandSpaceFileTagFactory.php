<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileTag;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpaceFileTag>
 */
final class BandSpaceFileTagFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'name' => self::faker()->word(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceFileTag::class;
    }
}
