<?php

declare(strict_types=1);

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\Forum;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ForumFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'description' => self::faker()->text(300),
            'forumCategory' => ForumCategoryFactory::new(),
            'position' => self::faker()->randomNumber(),
            'postNumber' => self::faker()->randomNumber(2),
            'slug' => self::faker()->slug(),
            'title' => self::faker()->text(150),
            'topicNumber' => self::faker()->randomNumber(2),
            'updateDatetime' => null,
        ];
    }

    public static function class(): string
    {
        return Forum::class;
    }
}
