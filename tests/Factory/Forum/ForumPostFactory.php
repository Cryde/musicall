<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumPost;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ForumPostFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'content' => self::faker()->text(),
            'creationDatetime' => self::faker()->dateTime(),
            'creator' => UserFactory::new(),
            'topic' => ForumTopicFactory::new(),
            'updateDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return ForumPost::class;
    }
}
