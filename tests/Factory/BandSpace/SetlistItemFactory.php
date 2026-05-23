<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\SetlistItem;
use App\Enum\BandSpace\SetlistItemType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SetlistItem>
 */
final class SetlistItemFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'setlist' => SetlistFactory::new(),
            'type' => SetlistItemType::Talk,
            'label' => self::faker()->sentence(2),
            'position' => 0,
        ];
    }

    public static function class(): string
    {
        return SetlistItem::class;
    }
}
