<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\Task;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Task>
 */
final class TaskFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'title' => self::faker()->sentence(3),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Normal,
            'createdBy' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return Task::class;
    }
}
