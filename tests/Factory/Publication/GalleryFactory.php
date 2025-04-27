<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Gallery;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class GalleryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
            'description' => self::faker()->text(),
            'publicationDatetime' => self::faker()->dateTime(),
            'slug' => self::faker()->text(255),
            'status' => self::faker()->numberBetween(1, 32767),
            'title' => self::faker()->text(255),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return Gallery::class;
    }
}
