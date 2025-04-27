<?php

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\ViewCache;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ViewCacheFactory extends PersistentProxyObjectFactory
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
