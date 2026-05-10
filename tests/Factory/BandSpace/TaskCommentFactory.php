<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TaskComment;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<TaskComment>
 */
final class TaskCommentFactory extends PersistentObjectFactory
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
