<?php declare(strict_types=1);

namespace App\Fixtures\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\Role;
use App\Fixtures\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
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
            'creationDatetime' => self::faker()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public static function class(): string
    {
        return BandSpaceMembership::class;
    }
}
