<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TaskCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaskCategory>
 */
final class TaskCategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'name' => self::faker()->word(),
            'color' => '#FF6B6B',
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return TaskCategory::class;
    }
}
