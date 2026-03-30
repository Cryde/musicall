<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\TaskActivity;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TaskActivity>
 */
final class TaskActivityFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'task' => TaskFactory::new(),
            'actor' => UserFactory::new(),
            'type' => 'status_changed',
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return TaskActivity::class;
    }
}
