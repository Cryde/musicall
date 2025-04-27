<?php

namespace App\Tests\Factory\User;

use App\Entity\Image\UserProfilePicture;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class UserProfilePictureFactory extends PersistentProxyObjectFactory
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
