<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\Role;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BandSpaceMembership>
 */
final class BandSpaceMembershipFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'user' => UserFactory::new(),
            'role' => Role::User,
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceMembership::class;
    }
}
