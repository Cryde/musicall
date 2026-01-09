<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\SocialAccount;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SocialAccount>
 */
final class SocialAccountFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'providerId' => self::faker()->uuid(),
            'email' => self::faker()->email(),
        ];
    }

    public static function class(): string
    {
        return SocialAccount::class;
    }
}
