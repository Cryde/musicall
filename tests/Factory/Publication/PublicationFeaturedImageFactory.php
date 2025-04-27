<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationFeaturedImage;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class PublicationFeaturedImageFactory extends PersistentProxyObjectFactory
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
        return PublicationFeaturedImage::class;
    }
}
