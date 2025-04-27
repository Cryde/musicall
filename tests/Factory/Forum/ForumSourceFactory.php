<?php

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumSource;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class ForumSourceFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'description' => self::faker()->text(255),
            'slug' => self::faker()->text(255),
        ];
    }

    public function asRoot()
    {
        return $this->with(['description' => 'Root source forum', 'slug' => 'root']);
    }

    public static function class(): string
    {
        return ForumSource::class;
    }
}
