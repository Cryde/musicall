<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Comment;

use App\Entity\Comment\CommentThread;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<CommentThread>
 */
final class CommentThreadFactory extends PersistentObjectFactory
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
