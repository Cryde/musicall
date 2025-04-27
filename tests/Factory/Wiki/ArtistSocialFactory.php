<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Wiki\ArtistSocial;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ArtistSocialFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'artist' => ArtistFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
            'type' => self::faker()->numberBetween(1, 32767),
            'url' => self::faker()->text(255),
        ];
    }

    public static function class(): string
    {
        return ArtistSocial::class;
    }
}
