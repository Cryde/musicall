<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Enum\BandSpace\InvitationStatus;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BandSpaceInvitation>
 */
final class BandSpaceInvitationFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'invitedBy' => UserFactory::new(),
            'email' => self::faker()->email(),
            'token' => bin2hex(random_bytes(32)),
            'status' => InvitationStatus::Pending,
            'creationDatetime' => self::faker()->dateTime(),
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ];
    }

    public static function class(): string
    {
        return BandSpaceInvitation::class;
    }
}
