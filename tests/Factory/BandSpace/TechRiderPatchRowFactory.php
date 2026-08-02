<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TechRiderPatchRow;
use App\Enum\BandSpace\TechRiderPatchDirection;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TechRiderPatchRow>
 */
final class TechRiderPatchRowFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'item' => TechRiderItemFactory::new(),
            'direction' => TechRiderPatchDirection::Input,
            // Pinned rather than faked: channel and position both participate in ordering and
            // uniqueness assertions, so a random value would make tests flake.
            'channel' => 1,
            'position' => 0,
        ];
    }

    public static function class(): string
    {
        return TechRiderPatchRow::class;
    }
}
