<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Wiki\Artist;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ArtistFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'biography' => self::faker()->text(),
            'countryCode' => self::faker()->text(3),
            'creationDatetime' => self::faker()->dateTime(),
            'labelName' => self::faker()->text(255),
            'members' => self::faker()->text(),
            'name' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public static function class(): string
    {
        return Artist::class;
    }
}
