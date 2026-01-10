<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\User;

use App\Entity\User\UserProfile;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<UserProfile>
 */
final class UserProfileFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bio' => self::faker()->optional(0.7)->paragraph(),
            'location' => self::faker()->optional(0.6)->city(),
            'isPublic' => true,
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    public static function class(): string
    {
        return UserProfile::class;
    }
}
