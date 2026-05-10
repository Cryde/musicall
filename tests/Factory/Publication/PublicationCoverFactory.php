<?php

declare(strict_types=1);

namespace App\Tests\Factory\Publication;

use App\Entity\Image\PublicationCover;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class PublicationCoverFactory extends PersistentObjectFactory
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
