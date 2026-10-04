<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\DeviceToken;
use App\Enum\User\DevicePlatform;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DeviceToken>
 */
final class DeviceTokenFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            // Shaped like a real FCM token: an instance id, a colon, then an APA91 blob.
            'token' => self::faker()->regexify('[A-Za-z0-9_-]{22}') . ':APA91b' . self::faker()->regexify('[A-Za-z0-9_-]{134}'),
            'platform' => DevicePlatform::Android,
        ];
    }

    public static function class(): string
    {
        return DeviceToken::class;
    }
}
