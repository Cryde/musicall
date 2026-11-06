<?php

declare(strict_types=1);

namespace App\Tests\Factory\Profile;

use App\Entity\Profile\ProfilePost;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProfilePost>
 */
final class ProfilePostFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'content' => self::faker()->sentence(),
            'isPinned' => false,
            'creationDatetime' => new \DateTimeImmutable('2026-11-01T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ProfilePost::class;
    }
}
