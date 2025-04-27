<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\GalleryImage;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class GalleryImageFactory extends PersistentProxyObjectFactory
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
        return GalleryImage::class;
    }
}
