<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\UserDailyActivity;
use App\Enum\User\ClientPlatform;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserDailyActivity>
 */
final class UserDailyActivityFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'day' => new \DateTimeImmutable('today'),
            'platform' => ClientPlatform::Web,
        ];
    }

    public static function class(): string
    {
        return UserDailyActivity::class;
    }
}
