<?php declare(strict_types=1);

namespace App\Fixtures\Factory\BandSpace;

use App\Entity\BandSpace\BandSpace;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<BandSpace>
 */
final class BandSpaceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
            'creationDatetime' => self::faker()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public static function class(): string
    {
        return BandSpace::class;
    }
}
