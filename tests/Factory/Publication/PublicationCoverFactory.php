<?php

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationCover;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class PublicationCoverFactory extends PersistentProxyObjectFactory
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
        return PublicationCover::class;
    }
}
