<?php

declare(strict_types=1);

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationImage;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class PublicationImageFactory extends PersistentObjectFactory
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
        return PublicationImage::class;
    }
}
