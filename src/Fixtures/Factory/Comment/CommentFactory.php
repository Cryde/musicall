<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Comment;

use App\Entity\Comment\Comment;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<Comment>
 */
final class CommentFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'content' => self::faker()->text(),
            'creationDatetime' => self::faker()->dateTime(),
            'thread' => CommentThreadFactory::new(),
        ];
    }

    public static function class(): string
    {
        return Comment::class;
    }
}
