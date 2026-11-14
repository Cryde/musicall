<?php

declare(strict_types=1);

namespace App\Tests\Factory\Profile;

use App\Entity\Profile\ProfilePostCommentLike;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Leaves the comment's stored `likeCount` as it is: a test sets it when the order or the count matters.
 *
 * @extends PersistentObjectFactory<ProfilePostCommentLike>
 */
final class ProfilePostCommentLikeFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'comment' => ProfilePostCommentFactory::new(),
            'user' => UserFactory::new(),
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00'),
        ];
    }

    public static function class(): string
    {
        return ProfilePostCommentLike::class;
    }
}
