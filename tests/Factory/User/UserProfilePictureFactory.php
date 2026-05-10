<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\Image\UserProfilePicture;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class UserProfilePictureFactory extends PersistentObjectFactory
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
        return UserProfilePicture::class;
    }
}
