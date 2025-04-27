<?php

namespace App\Tests\Factory\Wiki;

use App\Entity\Image\WikiArtistCover;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class WikiArtistCoverFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'imageName' => self::faker()->text(255),
            'imageSize' => self::faker()->randomNumber(),
            'updatedAt' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return WikiArtistCover::class;
    }
}
