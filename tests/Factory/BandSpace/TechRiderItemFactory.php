<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TechRiderItem;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TechRiderItem>
 */
final class TechRiderItemFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'techRider' => TechRiderFactory::new(),
            'title' => self::faker()->sentence(2),
            'position' => 0,
        ];
    }

    public static function class(): string
    {
        return TechRiderItem::class;
    }
}
