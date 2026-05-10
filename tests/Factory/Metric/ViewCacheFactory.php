<?php

declare(strict_types=1);

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\ViewCache;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ViewCacheFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'count' => self::faker()->randomNumber(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return ViewCache::class;
    }
}
