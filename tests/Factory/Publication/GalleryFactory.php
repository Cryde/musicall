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
            'description' => self::faker()->text(200),
            'publicationDatetime' => self::faker()->dateTime(),
            'slug' => self::faker()->slug(3),
            'status' => self::faker()->randomElement([Gallery::STATUS_ONLINE, Gallery::STATUS_DRAFT, Gallery::STATUS_PENDING]),
            'title' => self::faker()->text(100),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return Gallery::class;
    }
}
