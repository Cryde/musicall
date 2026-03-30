<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TaskComment;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaskComment>
 */
final class TaskCommentFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'task' => TaskFactory::new(),
            'author' => UserFactory::new(),
            'content' => self::faker()->paragraph(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return TaskComment::class;
    }
}
