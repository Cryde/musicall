<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Entity\User\Relation\UserBlock;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserBlock>
 */
final class UserBlockFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'blocker' => UserFactory::new(),
            'blocked' => UserFactory::new(),
        ];
    }

    public static function class(): string
    {
        return UserBlock::class;
    }
}
