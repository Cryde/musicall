<?php

namespace App\Tests\Factory\Metric;

use App\Entity\Metric\Vote;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

final class VoteFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'voteCache' => VoteCacheFactory::new(),
            'identifier' => self::faker()->sha256(),
            'value' => self::faker()->randomElement([1, -1]),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return Vote::class;
    }
}
