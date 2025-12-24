<?php declare(strict_types=1);

namespace App\Fixtures\Factory\BandSpace;

use App\Entity\BandSpace\BandSpace;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<BandSpace>
 */
final class BandSpaceFactory extends PersistentProxyObjectFactory
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
