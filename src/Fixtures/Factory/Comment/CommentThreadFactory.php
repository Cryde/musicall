<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Comment;

use App\Entity\Comment\CommentThread;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/** @codeCoverageIgnore */
final class CommentThreadFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'commentNumber' => self::faker()->randomNumber(),
            'creationDatetime' => self::faker()->dateTime(),
            'isActive' => self::faker()->boolean(),
        ];
    }

    public static function class(): string
    {
        return CommentThread::class;
    }
}
