<?php

namespace App\Tests\Factory\Publication;

use App\Entity\PublicationFeatured;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class PublicationFeaturedFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'description' => self::faker()->text(),
            'level' => self::faker()->numberBetween(1, 32767),
            'options' => [],
            'publication' => PublicationFactory::new(),
            'publicationDatetime' => self::faker()->dateTime(),
            'status' => self::faker()->numberBetween(1, 32767),
            'title' => self::faker()->text(255),
        ];
    }

    public static function class(): string
    {
        return PublicationFeatured::class;
    }
}
