<?php

declare(strict_types=1);

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\View;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ViewFactory extends PersistentObjectFactory
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
