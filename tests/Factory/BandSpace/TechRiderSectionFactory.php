<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TechRiderSection;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TechRiderSection>
 */
final class TechRiderSectionFactory extends PersistentObjectFactory
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
        return TechRiderSection::class;
    }
}
