<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceNote;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpaceNote>
 */
final class BandSpaceNoteFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'title' => self::faker()->sentence(3),
            'position' => 0,
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceNote::class;
    }
}
