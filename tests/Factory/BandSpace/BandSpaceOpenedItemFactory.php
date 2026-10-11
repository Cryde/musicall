<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceOpenedItem;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BandSpaceOpenedItem>
 */
final class BandSpaceOpenedItemFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'membership' => BandSpaceMembershipFactory::new(),
            'type' => BandSpaceSearchResultType::Task,
            'resourceId' => self::faker()->uuid(),
            'openedDatetime' => new \DateTimeImmutable(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceOpenedItem::class;
    }
}
