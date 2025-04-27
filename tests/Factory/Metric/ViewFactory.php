<?php

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\View;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ViewFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'identifier' => self::faker()->text(255),
            'viewCache' => ViewCacheFactory::new(),
        ];
    }

    public static function class(): string
    {
        return View::class;
    }
}
