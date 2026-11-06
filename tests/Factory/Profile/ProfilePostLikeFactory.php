<?php

declare(strict_types=1);

namespace App\Tests\Factory\Profile;

use App\Entity\Profile\ProfilePostLike;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProfilePostLike>
 */
final class ProfilePostLikeFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'post' => ProfilePostFactory::new(),
            'user' => UserFactory::new(),
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ProfilePostLike::class;
    }
}
