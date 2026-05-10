<?php

declare(strict_types=1);

namespace App\Tests\Factory\Forum;

use Zenstruck\Foundry\Factory;
use App\Entity\Forum\ForumSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ForumSourceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'description' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asRoot(): \App\Tests\Factory\Forum\ForumSourceFactory
    {
        return $this->with(['description' => 'Root source forum', 'slug' => 'root']);
    }

    public static function class(): string
    {
        return ForumSource::class;
    }
}
