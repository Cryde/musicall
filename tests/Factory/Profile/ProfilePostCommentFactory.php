<?php

declare(strict_types=1);

namespace App\Tests\Factory\Profile;

use App\Entity\Profile\ProfilePostComment;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProfilePostComment>
 */
final class ProfilePostCommentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'post' => ProfilePostFactory::new(),
            'author' => UserFactory::new(),
            'content' => self::faker()->sentence(),
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ProfilePostComment::class;
    }
}
