<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumTopic;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ForumTopicFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'author' => UserFactory::new(),
            'creationDatetime' => new \DateTime(),
            'forum' => ForumFactory::new(),
            'isLocked' => false,
            'postNumber' => self::faker()->randomNumber(),
            'slug' => self::faker()->text(255),
            'title' => self::faker()->text(255),
            'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
        ];
    }

    public static function class(): string
    {
        return ForumTopic::class;
    }
}
