<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumCategory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ForumCategoryFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'forumSource' => ForumSourceFactory::new()->asRoot(),
            'position' => self::faker()->randomNumber(),
            'title' => self::faker()->text(255),
        ];
    }

    public static function class(): string
    {
        return ForumCategory::class;
    }
}
